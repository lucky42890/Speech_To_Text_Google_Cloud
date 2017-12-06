<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Speech\SpeechClient;

use FFMpeg;

class UploadFileController extends Controller {
    public function index(){
        return view( 'uploadfile' );
    }

    public function uploadFileToGoogleStorage( $file ) {

        /*
            upload file to google storage
        */

        // general google storage information
        $bucketName = 'voicemail_42890';
        $objectName = $file->getClientOriginalName();
        $source = $file->getRealPath();

        // path of key file of service account from google cloud
        $keyPath = base_path().'/gcpConfig/Voicemail-google-storage-service.json';

        // information for google strage
        $projectId = 'voicemail-transcription-186618';
        $keyFile = json_decode( file_get_contents( $keyPath ), true );

        // create storage client
        $storage = new StorageClient([
            'projectId' => $projectId,
            'keyFile'   => $keyFile
        ]);

        // make the api call
        $file = fopen( $source, 'r' );
        $bucket = $storage->bucket( $bucketName );
        $object = $bucket->upload( $file, [
            'name' => $objectName
        ]);
    }

    public function recognizeFileWithGoogleSpeech( $file ) {
        
        /*
            convert audio file according to the format of google speech
        */

        $rate = 16000;

        // get current audio file
        $ffmpeg = FFMpeg\FFMpeg::create();
        $audio = $ffmpeg->open( $file->getRealPath() );

        // set audio type wav LINEAR16( audioCodec : s16le ), single channel
        $format = new FFMpeg\Format\Audio\Wav();
        $format->setAudioChannels(1);

        // set sample rate
        $audio->filters()->resample( $rate );

        // save 1.wav in public folder
        $audio->save( $format, '1.wav' );

        /*
            get script from file using google speech api
        */

        // path of file to recognize, converted audio file
        $source = base_path().'/public/1.wav';

        // information for google speech
        $projectId = 'voicemail-transcription-186618';
        $languageCode = 'en-US';
        $options = [
            'encoding' => 'LINEAR16',
            'sampleRateHertz' => 16000,
        ];

        // path of key file of service account from google cloud
        $keyPath = base_path().'/gcpConfig/Voicemail-google-speech-service.json';
        $keyFile = json_decode( file_get_contents( $keyPath ), true );

        // create the speech client
        $speech = new SpeechClient([
            'projectId' => $projectId,
            'languageCode' => $languageCode,
            'keyFile'   => $keyFile
        ]);

        // make the api call
        $results = $speech->recognize(
            fopen($source, 'r'),
            $options
        );

        $this->saveScript( $results );
    }

    public function saveScript( $results ) {

        $fileName = "script.txt";

        foreach ( $results as $result ) {
            $alternative = $result->alternatives()[0];
            file_put_contents( 'script.txt', $alternative['transcript'], FILE_APPEND );
        }
    }

    public function showUploadFile( Request $request ){

        $file = $request->file( 'file' );
    
        //Display File Name
        echo 'File Name: '.$file->getClientOriginalName();
        echo '<br>';
    
        //Display File Extension
        echo 'File Extension: '.$file->getClientOriginalExtension();
        echo '<br>';
    
        //Display File Real Path
        echo 'File Real Path: '.$file->getRealPath();
        echo '<br>';
    
        //Display File Size
        echo 'File Size: '.$file->getSize();
        echo '<br>';
    
        //Display File Mime Type
        echo 'File Mime Type: '.$file->getMimeType();

        $this->recognizeFileWithGoogleSpeech( $file );
    }

}
<?php

require __DIR__ . "/../vendor/autoload.php";

new \ElxDigital\AmazonService\AmazonService(
    "<<HOST HERE>>",
    "<<ACCESS KEY HERE>>",
    "<<SECRET KEY HERE>>"
);

try {
    $bucketName = "test-bucket";
    if ((new \ElxDigital\AmazonService\Services\S3\Bucket())->createBucket($bucketName)) {
        echo "✅ Bucket created <br />";

        $listBuckets = (new \ElxDigital\AmazonService\Services\S3\Bucket())->listBuckets();
        if (!$listBuckets) {
            throw new Exception("❌ Error listing buckets");
        }

        echo "✅ Buckets listed <br />";

        $fileToUpload = __DIR__ . "/1920x1080.webp";
        $fileToUploadName = basename($fileToUpload);
        $fileToUploadSize = filesize($fileToUpload);

        $fileToUploadHandle = fopen($fileToUpload, "r");
        $fileToUploadContent = fread($fileToUploadHandle, $fileToUploadSize);
        fclose($fileToUploadHandle);

        if ((new \ElxDigital\AmazonService\Services\S3\File())->createObject($bucketName, $fileToUploadName, $fileToUploadContent)) {
            echo "✅ Object uploaded <br />";
        } else {
            throw new Exception("❌ Error uploading object");
        }

        $listObjects = (new \ElxDigital\AmazonService\Services\S3\File())->listObjects($bucketName);
        if (!$listObjects) {
            throw new Exception("❌ Error listing objects");
        }

        echo "✅ Objects listed <br />";

        if ((new \ElxDigital\AmazonService\Services\S3\File())->deleteObject($bucketName, $fileToUploadName)) {
            echo "✅ Object deleted <br />";
        } else {
            throw new Exception("❌ Error deleted object");
        }

        if ((new \ElxDigital\AmazonService\Services\S3\Bucket())->deleteBucket($bucketName)) {
            echo "✅ Bucket deleted <br />";
        } else {
            throw new Exception("❌ Error deleting bucket");
        }

    } else {
        throw new Exception("❌ Error creating bucket");
    }
} catch (Exception $e) {
    echo $e->getMessage();
}








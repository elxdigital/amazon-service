<?php

namespace ElxDigital\AmazonService\Services\S3;

use ElxDigital\AmazonService\AmazonService;
use Exception;

class Bucket
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        if(!AmazonService::getHost()) {
            throw new Exception("AmazonService instance not found");
        }

        AmazonService::setService('s3');
    }

    /**
     * @return int|object|string|array
     * @throws Exception
     */
    public function listBuckets(): int|object|string|array
    {
        AmazonService::setEndpoint("/");
        AmazonService::setParams('');
        AmazonService::get();

        return AmazonService::getCallback();
    }

    /**
     * @param string $bucketName
     * @return bool
     * @throws Exception
     */
    public function createBucket(string $bucketName): bool
    {
        if(!$bucketName) {
            throw new Exception("Bucket name is required");
        }

        AmazonService::setEndpoint("/{$bucketName}");
        AmazonService::setParams('');
        AmazonService::put();

        return AmazonService::getCallback();
    }

    /**
     * @param string $bucketName
     * @return bool
     * @throws Exception
     */
    public function deleteBucket(string $bucketName): bool
    {
        if(!$bucketName) {
            throw new Exception("Bucket name is required");
        }

        AmazonService::setEndpoint("/{$bucketName}");
        AmazonService::setParams('');
        AmazonService::delete();

        return AmazonService::getCallback();
    }
}

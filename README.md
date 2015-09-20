## Direct Upload to S3 (using AWS Signature v4 & PHP)

[![GitHub license](https://img.shields.io/github/license/mashape/apistatus.svg?style=flat-square)]()

The source code to an article originally on Designed by a Turtle: Direct Upload to S3 (using AWS Signature v4 & PHP)

http://www.designedbyaturtle.co.uk/2015/direct-upload-to-s3-using-aws-signature-v4-php/

--

### How to use it

Fill in these with your AWS details.

```
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET', '');
```

*Remember not to commit these to a public repository*

Call the function and pass in your bucket and the bucket's region name (more details on finding it here: http://amzn.to/1FtPG6r)

    $s3FormDetails = getS3Details('', '');

Make sure your bucket has the correct CORS Configuration, something like:

```
<?xml version="1.0" encoding="UTF-8"?>
<CORSConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
    <CORSRule>
        <AllowedOrigin>*</AllowedOrigin>
        <AllowedMethod>GET</AllowedMethod>
        <AllowedMethod>POST</AllowedMethod>
        <MaxAgeSeconds>3000</MaxAgeSeconds>
        <AllowedHeader>Authorization</AllowedHeader>
    </CORSRule>
</CORSConfiguration>

```

--

### How it Works

##### Step 1 - Create the Scope
```
$scope = [
    S3_KEY,
    $shortDate,
    S3_REGION,
    $service,
    $requestType
];
$credentials = implode('/', $scope);
```
##### Step 2 - Create the Policy
```
$policy = [
    'expiration' => gmdate('Y-m-d\TG:i:s\Z', strtotime('+6 hours')),
    'conditions' => [
        ['bucket' => $s3Bucket],
        ['acl' => $acl],
        ['starts-with', '$key', ''],
        ['starts-with', '$Content-Type', ''],
        ['success_action_status' => $successStatus],
        ['x-amz-credential' => $credentials],
        ['x-amz-algorithm' => $algorithm],
        ['x-amz-date' => $date],
        ['x-amz-expires' => $expires],
    ]
];
$base64Policy = base64_encode(json_encode($policy));
```  
##### Step 3 - Sign the Keys with sha256
```
$dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . AWS_SECRET, true);
$dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
$dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
$signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);

$signature = hash_hmac('sha256', $base64Policy, $signingKey);
```

### Notes

* All dates are in UTC - so the gmdate() function is used in PHP.
* If you want the upload to be public then change the acl from private to public-read.

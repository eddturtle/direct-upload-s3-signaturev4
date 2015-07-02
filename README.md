## Direct Upload to S3 (using AWS Signature v4 & PHP)

[![GitHub license](https://img.shields.io/github/license/mashape/apistatus.svg?style=flat-square)]()

The source code to an article originally on Designed by a Turtle: Direct Upload to S3 (using AWS Signature v4 & PHP)

http://www.designedbyaturtle.co.uk/2015/direct-upload-to-s3-using-aws-signature-v4-php/

--

### Step 1 - Fill in your Details

To get this script to work you'll have to fill in your AWS details.
S3_Bucket = The Bucket's name in lowercase.
S3_Key = Your AWS Access Key.
S3_SECRET = Your AWS Secret Key.
S3_REGION = the S3 region where you bucket is placed.

*Remember not to commit them to a public repository*

#### Step 2 - Create the Scope
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
#### Step 3 - Create the Policy
```
$policy = [
    'expiration' => gmdate('Y-m-d\TG:i:s\Z', strtotime('+6 hours')),
    'conditions' => [
        ['bucket' => S3_BUCKET],
        ['acl' => S3_ACL],
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
#### Step 4 - Sign the Keys with sha256
```
$dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . S3_SECRET, true);
$dateRegionKey = hash_hmac('sha256', S3_REGION, $dateKey, true);
$dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
$signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);
```

#### Notes

* All dates are in UTC - so the gmdate() function is used in PHP.
* If you want the upload to be public then change the S3_ACL from private to public-read.

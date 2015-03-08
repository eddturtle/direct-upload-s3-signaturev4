<?php

// Fill These In!
define('S3_BUCKET', '');
define('S3_KEY', '');
define('S3_SECRET', '');
define('S3_REGION', 'eu-central-1'); // S3 region name: http://amzn.to/1FtPG6r
define('S3_ACL', 'private');         // File permissions: http://amzn.to/18s9Gv7
// Stop Here

$algorithm = "AWS4-HMAC-SHA256";
$service = "s3";
$date = date('Ymd\THis\Z');
$shortDate = date('Ymd');
$requestType = "aws4_request";
$expires = '86400'; // 24 Hours

$scope = [
    S3_KEY,
    $shortDate,
    S3_REGION,
    $service,
    $requestType
];
$credentials = implode('/', $scope);

$policy = [
    'expiration' => date('Y-m-d\TG:i:s\Z', strtotime('+6 hours')),
    'conditions' => [
        ['bucket' => S3_BUCKET],
        ['acl' => S3_ACL],
        [
            'starts-with',
            '$key',
            ''
        ],
        ['success_action_status' => '201'],
        ['x-amz-credential' => $credentials],
        ['x-amz-algorithm' => $algorithm],
        ['x-amz-date' => $date],
        ['x-amz-expires' => $expires],
    ]
];
$base64Policy = base64_encode(json_encode($policy));

// Signing Keys
$dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . S3_SECRET, true);
$dateRegionKey = hash_hmac('sha256', S3_REGION, $dateKey, true);
$dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
$signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);

// Signature
$signature = hash_hmac('sha256', $base64Policy, $signingKey);

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Direct Upload Example</title>
        <style>
            .progress {
                position: relative;
                width: 100%;
                height: 15px;
                background: #C7DA9F;
                border-radius: 10px;
                overflow: hidden;
            }

            .bar {
                position: absolute;
                top: 0; left: 0;
                width: 0; height: 15px;
                background: #85C220;
            }
            .bar.red {
                background: tomato;
            }
        </style>
    </head>
    <body>

        <!-- Direct Upload to S3 -->
        <!-- URL prefix (//) means either HTTP or HTTPS (depending on which is being currently used) -->
        <form action="<?php echo "http://" . S3_BUCKET . ".s3." . S3_REGION . ".amazonaws.com/"; ?>"
              method="POST"
              enctype="multipart/form-data"
              class="direct-upload">

            <!-- We'll specify these variables with PHP -->
            <!-- Note: Order of these is Important -->
            <input type="hidden" name="key" value="${filename}">
            <input type="hidden" name="acl" value="<?php echo S3_ACL; ?>">
            <input type="hidden" name="success_action_status" value="201">
            <input type="hidden" name="policy" value="<?php echo $base64Policy; ?>">

            <input type="hidden" name="X-amz-algorithm" value="<?php echo $algorithm; ?>">
            <input type="hidden" name="X-amz-credential" value="<?php echo $credentials; ?>">
            <input type="hidden" name="X-amz-date" value="<?php echo $date; ?>">
            <input type="hidden" name="X-amz-expires" value="<?php echo $expires; ?>">
            <input type="hidden" name="X-amz-signature" value="<?php echo $signature; ?>">

            <input type="file" name="file"/>

            <!-- Progress Bar to show upload completion percentage -->
            <div class="progress">
                <div class="bar"></div>
            </div>
        </form>

        <!-- Used to Track Upload within our App -->
        <form action="server.php" method="POST">
            <input type="hidden" name="upload_original_name" id="upload_original_name"/>

            <label for="upload_custom_name">Name:</label><br/>
            <input type="text" name="upload_custom_name" id="upload_custom_name"/><br/>

            <input type="submit" value="Save"/>
        </form>

        <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
        <script src="//code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
        <script src="fileupload/jquery.fileupload.js"></script>
        <script>
            $(document).ready(function () {
                $('.direct-upload').each(function () {
                    var form = $(this);
                    form.fileupload({
                        url: form.attr('action'),
                        type: 'POST',
                        datatype: 'xml',
                        add: function (event, data) {

                            // Message on unLoad.
                            // Shows 'Are you sure you want to leave message', just to confirm.
                            window.onbeforeunload = function () {
                                return 'You have unsaved changes.';
                            };

                            // Actually submit to form, sending the data.
                            data.submit();
                        },
                        progress: function (e, data) {
                            // This is what makes everything really cool, thanks to that callback
                            // you can now update the progress bar based on the upload progress.
                            var percent = Math.round((data.loaded / data.total) * 100);
                            $('.bar').css('width', percent + '%');
                        },
                        fail: function (e, data) {
                            // Remove the 'unsaved changes' message.
                            window.onbeforeunload = null;
                            $('.bar').css('width', '100%').addClass('red');
                        },
                        done: function (event, data) {
                            window.onbeforeunload = null;
                            // Fill the name field with the file's name.
                            $('#upload_original_name').val(data.originalFiles[0].name);
                            $('#upload_custom_name').val(data.originalFiles[0].name);
                        }
                    });
                });
            });
        </script>
    </body>
</html>
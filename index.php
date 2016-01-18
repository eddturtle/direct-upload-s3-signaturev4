<?php


// TODO Enter your AWS credentials
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET', '');

// TODO Enter your bucket and region details (see details below)
$s3FormDetails = getS3Details('', '');


/**
 * Get all the necessary details to directly upload a private file to S3
 * asynchronously with JavaScript.
 *
 * @param string $s3Bucket your bucket's name on s3.
 * @param string $region   the bucket's location, see here for details: http://amzn.to/1FtPG6r
 * @param string $acl      the visibility/permissions of your file, see details: http://amzn.to/18s9Gv7
 *
 * @return array ['url', 'inputs'] the forms url to s3 and any inputs the form will need.
 */
function getS3Details($s3Bucket, $region, $acl = 'private') {

    // Options and Settings
    $algorithm = "AWS4-HMAC-SHA256";
    $service = "s3";
    $date = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');
    $requestType = "aws4_request";
    $expires = '86400'; // 24 Hours
    $successStatus = '201';
    $url = '//' . $s3Bucket . '.' . $service . '-' . $region . '.amazonaws.com';

    // Step 1: Generate the Scope
    $scope = [
        AWS_ACCESS_KEY,
        $shortDate,
        $region,
        $service,
        $requestType
    ];
    $credentials = implode('/', $scope);

    // Step 2: Making a Base64 Policy
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

    // Step 3: Signing your Request (Making a Signature)
    $dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . AWS_SECRET, true);
    $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
    $dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
    $signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);

    $signature = hash_hmac('sha256', $base64Policy, $signingKey);

    // Step 4: Build form inputs
    // This is the data that will get sent with the form to S3
    $inputs = [
        'Content-Type' => '',
        'acl' => $acl,
        'success_action_status' => $successStatus,
        'policy' => $base64Policy,
        'X-amz-credential' => $credentials,
        'X-amz-algorithm' => $algorithm,
        'X-amz-date' => $date,
        'X-amz-expires' => $expires,
        'X-amz-signature' => $signature
    ];

    return compact('url', 'inputs');
}

?>

<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Direct Upload Example</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/3.0.3/normalize.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>

        <div class="container">

            <h1>Direct Upload</h1>

            <!-- Direct Upload to S3 Form -->
            <form action="<?php echo $s3FormDetails['url']; ?>"
                  method="POST"
                  enctype="multipart/form-data"
                  class="direct-upload">

                <?php foreach ($s3FormDetails['inputs'] as $name => $value) { ?>
                    <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
                <?php } ?>

                <!-- Key is the file's name on S3 and will be filled in with JS -->
                <input type="hidden" name="key" value="">
                <input type="file" name="file" multiple>

                <!-- Progress Bars to show upload completion percentage -->
                <div class="progress-bar-area"></div>

            </form>

            <!-- This area will be filled with our results (mainly for debugging) -->
            <div>
                <h3>Files</h3>
                <textarea id="uploaded"></textarea>
            </div>

        </div>

        <!-- Start of the JavaScript -->
        <!-- Load jQuery & jQuery UI (Needed for the FileUpload Plugin) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>

        <!-- Load the FileUpload Plugin (more info @ https://github.com/blueimp/jQuery-File-Upload) -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.5.7/jquery.fileupload.js"></script>

        <script>
            $(document).ready(function () {

                // Assigned to variable for later use.
                var form = $('.direct-upload');
                var filesUploaded = [];

                // Place any uploads within the descending folders
                // so ['test1', 'test2'] would become /test1/test2/filename
                var folders = [];

                form.fileupload({
                    url: form.attr('action'),
                    type: form.attr('method'),
                    datatype: 'xml',
                    add: function (event, data) {

                        // Give the file which is being uploaded it's current content-type (It doesn't retain it otherwise)
                        // and give it a unique name (so it won't overwrite anything already on s3).
                        var file = data.files[0];
                        var filename = Date.now() + '.' + file.name.split('.').pop();
                        form.find('input[name="Content-Type"]').val(file.type);
                        form.find('input[name="key"]').val((folders.length ? folders.join('/') + '/' : '') + filename);

                        // Show warning message if your leaving the page during an upload.
                        window.onbeforeunload = function () {
                            return 'You have unsaved changes.';
                        };

                        // Actually submit to form to S3.
                        data.submit();

                        // Show the progress bar
                        // Uses the file size as a unique identifier
                        var bar = $('<div class="progress" data-mod="'+file.size+'"><div class="bar"></div></div>');
                        $('.progress-bar-area').append(bar);
                        bar.slideDown('fast');
                    },
                    progress: function (e, data) {
                        // This is what makes everything really cool, thanks to that callback
                        // you can now update the progress bar based on the upload progress.
                        var percent = Math.round((data.loaded / data.total) * 100);
                        $('.progress[data-mod="'+data.files[0].size+'"] .bar').css('width', percent + '%').html(percent+'%');
                    },
                    fail: function (e, data) {
                        // Remove the 'unsaved changes' message.
                        window.onbeforeunload = null;
                        $('.progress[data-mod="'+data.files[0].size+'"] .bar').css('width', '100%').addClass('red').html('');
                    },
                    done: function (event, data) {
                        window.onbeforeunload = null;

                        // Upload Complete, show information about the upload in a textarea
                        // from here you can do what you want as the file is on S3
                        // e.g. save reference to your server / log it, etc.
                        var original = data.files[0];
                        var s3Result = data.result.documentElement.children;
                        filesUploaded.push({
                            "original_name": original.name,
                            "s3_name": s3Result[2].innerHTML,
                            "size": original.size,
                            "url": s3Result[0].innerHTML
                        });
                        $('#uploaded').html(JSON.stringify(filesUploaded, null, 2));
                    }
                });
            });
        </script>
    </body>
</html>
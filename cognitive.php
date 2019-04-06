<?php

require_once 'vendor/autoload.php';
require_once "./random_string.php";

use HTTP\Request2;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;

$connectionString = "DefaultEndpointsProtocol=https;AccountName=".getenv('ACCOUNT_NAME').";AccountKey=".getenv('ACCOUNT_KEY');

// Create blob client.
$blobClient = BlobRestProxy::createBlobService($connectionString);
$containerName = "adwicontainer";
$data = array();

if (isset($_POST['submit'])) {
    try {
        if ($_POST['submit'] == 'Upload') {
            //Upload blob
            $fileToUpload = $_FILES["image"]["name"];
            $content      = fopen($_FILES["image"]["tmp_name"], "r");
            $blobClient->createBlockBlob($containerName, $fileToUpload, $content);
        } else if ($_POST['submit'] == 'Delete') {
            //Delete blob
            $fileToUpload = $_POST['url'];
            $blobClient->deleteBlob($containerName, $fileToUpload);
        }
    } catch(ServiceException $e) {
        // Handle exception based on error codes and messages.
        // Error codes and messages are here:
        // http://msdn.microsoft.com/library/azure/dd179439.aspx
        $code = $e->getCode();
        $error_message = $e->getMessage();
        echo $code.": ".$error_message."<br />";
    } catch(InvalidArgumentTypeException $e) {
        // Handle exception based on error codes and messages.
        // Error codes and messages are here:
        // http://msdn.microsoft.com/library/azure/dd179439.aspx
        $code = $e->getCode();
        $error_message = $e->getMessage();
        echo $code.": ".$error_message."<br />";
    }
}

try {
    // List blobs.
    $listBlobsOptions = new ListBlobsOptions();
    do{
        $result = $blobClient->listBlobs($containerName, $listBlobsOptions);
        foreach ($result->getBlobs() as $blob) {
            $data[] = array(
                'name' => $blob->getName(),
                'url' => $blob->getUrl()
            );
        }
    
        $listBlobsOptions->setContinuationToken($result->getContinuationToken());
    } while($result->getContinuationToken());
} catch(ServiceException $e) {
    // Handle exception based on error codes and messages.
    // Error codes and messages are here:
    // http://msdn.microsoft.com/library/azure/dd179439.aspx
    $code = $e->getCode();
    $error_message = $e->getMessage();
    echo $code.": ".$error_message."<br />";
} catch(InvalidArgumentTypeException $e) {
    // Handle exception based on error codes and messages.
    // Error codes and messages are here:
    // http://msdn.microsoft.com/library/azure/dd179439.aspx
    $code = $e->getCode();
    $error_message = $e->getMessage();
    echo $code.": ".$error_message."<br />";
}

if (isset($_POST['submit']) && $_POST['submit'] == 'Analyze') {
    $uriBase = 'https://southeastasia.api.cognitive.microsoft.com/vision/v2.0/';
    $imageUrl = $_POST['url'];
    $ocpApimSubscriptionKey = getenv('SAS_KEY');

    $request = new HTTP_Request2($uriBase . '/analyze');
    $url = $request->getUrl();

    $headers = array(
        // Request headers
        'Content-Type' => 'application/json',
        'Ocp-Apim-Subscription-Key' => $ocpApimSubscriptionKey
    );
    $request->setHeader($headers);

    $parameters = array(
        // Request parameters
        'visualFeatures' => 'Categories,Description',
        'details' => '',
        'language' => 'en'
    );
    $url->setQueryVariables($parameters);

    $request->setMethod(HTTP_Request2::METHOD_POST);

    // Request body parameters
    $body = json_encode(array('url' => $imageUrl));

    // Request body
    $request->setBody($body);

    try {
        $response = $request->send();
        $analyze[$imageUrl] = $response->getBody();
    } catch (HttpException $ex){
        echo "<pre>" . $ex . "</pre>";
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Hello, Bootstrap Table!</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.14.2/dist/bootstrap-table.min.css">
    <style>
        .starter-template {
            padding: 3rem 1.5rem;
            text-align: center;
        }
    </style>
  </head>
  <body>
    <main role="main" class="container">
        <div class="starter-template">
            <h1>Azure Cognitive Service</h1>
            <p class="lead">This page created to complete<br/><a href="https://www.dicoding.com/academies/83">Menjadi Azure Cloud Developer</a><br/>&copy; 2019 @adwiarifin</p>
        </div>

        <div class="upload-file">
            <form method="POST" enctype="multipart/form-data">
                Upload Image: 
                <input type="file" name="image" />
                <input type="submit" name="submit" value="Upload" />
            </form>
        </div>

        <table data-toggle="table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Image</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach($data as $blob): ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><img src="<?php echo $blob['url']; ?>" alt="<?php echo $blob['name']; ?>" width="200"></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="url" value="<?php echo $blob['url']; ?>" />
                        <input type="submit" name="submit" value="Analyze"/>
                        <input type="submit" name="submit" value="Delete"/>
                    </form>
                    <?php echo isset($analyze[$blob['url']]) ? "<pre>" .json_encode(json_decode($analyze[$blob['url']]), JSON_PRETTY_PRINT) . "</pre>" : ""?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
    </main>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/bootstrap-table@1.14.2/dist/bootstrap-table.min.js"></script>
  </body>
</html>
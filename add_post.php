<?php

$pwd  = getenv('MYPASS', true) ?: getenv('MYPASS');
define('ACCESS_PASSWORD', $pwd);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for the correct password
    if (!isset($_POST['password']) || $_POST['password'] !== ACCESS_PASSWORD) {
        echo "Access denied.";
        exit;
    }

    // Load the XML file
    $xml = simplexml_load_file('microblog.xml');
    
    // Check if loading the XML was successful
    if ($xml === false) {
        echo "Failed to load XML file.";
        exit;
    }

    // Access the <channel> element
    $channel = $xml->channel;

    // Determine the new GUID
    $lastGuid = 0;
    if (isset($channel->item) && count($channel->item) > 0) {
        // Get the highest existing GUID
        $lastGuid = (int)$channel->item[count($channel->item) - 1]->guid;
    }
    $newGuid = $lastGuid + 1;

    // Create a new <item> element
    $item = $channel->addChild('item');
    $item->addChild('guid', $newGuid);

    // Add the current date and time
    $item->addChild('pubDate', date('r'));

    // Initialize image path variable
    $imagePath = '';

    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/';
        $uploadFile = $uploadDir . basename($_FILES['image']['name']);
        
        // Ensure the upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Get the image extension
        $ext = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));

        // Load the uploaded image
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($_FILES['image']['tmp_name']);
                break;
            case 'png':
                $image = imagecreatefrompng($_FILES['image']['tmp_name']);
                break;
            case 'gif':
                $image = imagecreatefromgif($_FILES['image']['tmp_name']);
                break;
            default:
                echo "Unsupported image type.";
                exit;
        }

        if ($image !== false) {
            // Set the path for the WebP image
            $webpPath = $uploadDir . pathinfo($uploadFile, PATHINFO_FILENAME) . '.webp';

            // Convert the image to WebP with 50% quality
            if (imagewebp($image, $webpPath, 50)) {
                $imagePath = htmlspecialchars($webpPath, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            } else {
                echo "Failed to convert image to WebP.";
                exit;
            }

            // Free up memory
            imagedestroy($image);
        } else {
            echo "Failed to load image.";
            exit;
        }
    }

    // Add the description with encoded HTML
    $description = htmlspecialchars($_POST['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
    if ($imagePath) {
        $description .= '<br><img src="' . $imagePath . '">';
    }
    $item->addChild('description', $description);

    // Convert SimpleXML object to DOMDocument for pretty-printing
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());

    // Save the updated XML back to the file
    $result = $dom->save('microblog.xml');
    if (!$result) {
        echo "Failed to write to microblog.xml";
    } else {
        echo "Post added successfully!";
    }
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microblog Form</title>
</head>
<body>
    <h1>Add a New Post</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="description">Description:</label><br>
        <textarea name="description" id="description" rows="4" cols="50"></textarea><br><br>

        <label for="image">Upload Image (optional):</label><br>
        <input type="file" name="image" id="image"><br><br>

        <label for="password">Password:</label><br>
        <input type="password" name="password" id="password"><br><br>

        <input type="submit" value="Add Post">
    </form>
</body>
</html>

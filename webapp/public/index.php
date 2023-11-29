<?php
  /**
   * Secret key used to protect the downloads. This stops the user
   * from downloading full versions of images without paying!
   */
  define( 'SECRET_KEY', getenv('SECRET_KEY') );

  /**
   * Generates a download key for set of parameters.
   */
  function getDownloadKeyFor( $params ){
    return sha1( SECRET_KEY . urldecode( $params ) );
  }

  /**
   * Checks that the provided download key is valid for the query string.
   */
  function isDownloadKeyValid(){
    // Remove the key= parameter from the query string:
    $restOfParams = preg_replace('/&key=[a-f0-9]+/', '', $_SERVER['QUERY_STRING']);
    // Generate a valid download key for those parameters:
    $validDownloadKey = getDownloadKeyFor( $restOfParams );
    // Check whether the provided key matches the valid one:
    return $_GET['key'] === $validDownloadKey;
  }

  /**
   * Returns an array of images to show to the user, along with the
   * secret download keys if applicable.
   */
  function getImages(){
    // (if this were a real system, it'd generate this list dynamically)
    return [
      [
        // the user has paid for the first image, so it has a download_key:
        'file' => 'free',
        'title' => 'Free image',
        'price' => 0,
        'download_key' => getDownloadKeyFor( 'download=free' ),
      ],
      [
        // the user has not paid for the second image, so it does not have a download_key:
        'file' => 'valuable',
        'title' => 'Valuable image',
        'price' => 9999
      ],
    ];
  }

  // If the user is trying to purchase an image, handle that:
  if( isset( $_GET['purchase'] ) ) {
    // For demo purposes, let's assume the user NEVER has enough money for this.
    // Guess they'll have to find a way to steal it!
    header( 'HTTP/1.0 402 Payment Required' );
    echo 'Unable to take sufficient funds from your account.';
    die();    
  }

  // If the user is trying to download an image, handle that:
  if( isset( $_GET['download'] ) ) {
    // First, we check that they've provided a valid download key for that image:
    if( ! isDownloadKeyValid() ) {
      // If not, we show an error message:
      header( 'HTTP/1.0 403 Forbidden' );
      echo 'You need to purchase the image before downloading.';
      die();
    }
    // Download key checks out: deliver them the high-resolution version of the image:
    header('Content-type: image/jpeg');
    readfile( '../private/' . $_GET['download'] . '.jpg' );
    die();
  }

  // Otherwise, render the page as normal:
?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Images R Us</title>
  <style>
    body {
      font-family: Seravek, 'Gill Sans Nova', Ubuntu, Calibri, 'DejaVu Sans', source-sans-pro, sans-serif;
      margin: 0;
      background: #ffffff;
      color: #333333;
      font-size: 18px;
    }
    header {
      background: #09056f;
      color: white;
      padding: 0.5em;
    }
    main {
      padding: 1em;
    }
    .title {
      font-size: 3em;
      font-weight: bold;
      margin: 0;
      text-align: center;
    }
    .images {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      gap: 1em;
    }
    .image {
      display: flex;
      flex-direction: column;
      gap: 0.5em;
    }
    h2 {
      margin: 0;
    }
  </style>
</head>
<body>
  <header>
    <p class="title">Images R Us</p>
  </header>
  <main>
    <h1>Image Library</h1>
    <p>
      The following images are available to download:
    </p>
    <ul class="images">
      <?php foreach( getImages() as $image ) { ?>
        <li class="image">
          <img src="thumbnails/<?php echo $image['file']; ?>.jpg" alt="Sample of <?php echo $image['title']; ?>" />
          <h2><?php echo $image['title']; ?></h2>
          <p>
            <?php if( isset( $image[ 'download_key' ] ) ) { ?>
              <a target="_blank" href="/?download=<?php echo $image['file']; ?>&key=<?php echo $image['download_key']; ?>">
                Download
              </a>
            <?php } else { ?>
              <a href="/?purchase=<?php echo $image['file']; ?>">
                Purchase for £<?php echo number_format( $image['price'] ); ?>
              </a>
            <?php } ?>
          </p>
        </li>
      <?php } ?>
    </ul>
  </main>
</body>
</html>
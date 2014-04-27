<?php header("Content-type: text/css"); ?>

div.osfx-shownote-block {
  line-height: 1.4em;
}

.osfx-shownote-item:after {
  content: " — ";
}

.osfx-shownote-item:last-of-type:after {
  content: "";
}

h2.osfx-chapter {
  font-size: 1.3em;
  margin: 1em 0px 1em 0px;
  display: block;
}

.osfx-timestamp {
  font-family: Courier;
  font-weight: normal;
  font-size: 0.7em;
  margin-left: 0.4em;
}

.osfx-shownote-item {
  background-repeat: no-repeat;
  background-position: 0 center;
  margin-left: 4px;
  background-size: 16px 16px;
  list-style-type: none;
}

<?php

  $icon_dir = opendir('./icons/BitmapWebIcons');
  while (false !== ($file = readdir( $icon_dir ) ) ) {
    if ( $file !== '.' && $file !== '..' && $file !== '.git' ) {
      $name = substr( $file, 0, strripos( $file, '.' ) );
      ?>
       .osfx-shownote-item.<?php echo $name ?> {
         background-image: url('./icons/BitmapWebIcons/<?php echo $file; ?>');
         padding-left: 20px;
      }
      <?php
    }
  }
?>
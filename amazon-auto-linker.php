<?php
/*
Plugin Name: Amazon Auto Linker
Plugin URI: http://www.automaticlinker.com/amazon/
Description: Find keywords on your site and hyperlink them with Amazon associate links.
Author: Seb Hovsepian
Version: 1.2
Author URI: http://www.automaticlinker.com/amazon/
*/
define('AAL_BASE_URL', $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__));
define('AAL_CACHE', false);

require_once('main.class.php');
require_once('parser.class.php');

$aal = new AAL_Main(dirname(__FILE__), plugin_dir_url(__FILE__));
$aal->setDebug(false);
$aal->setCache(true);


add_action('admin_menu', 'aal_settings');

register_activation_hook(__FILE__, array($aal, 'activationTasks'));
register_deactivation_hook(__FILE__, array($aal, 'deactivationTasks'));


function aal_settings()
{

  add_options_page(__('Amazon Auto Linker', 'aal'), __('Amazon Auto Linker', 'aal'), 'manage_options', __FILE__, 'aal_settings_page');
}


function aal_settings_page()
{
  global $wpdb, $aal;

  ?>
<div class="wrap">
  <h2><?php _e("Amazon Auto Linker"); ?></h2><br/>

  <?php


  if ($_POST['aal_update_db']) {
    echo '<h3>Updating keywords database...</h3>';

    try {
      $aal->setCurlTimeout(120);
      $updated = $aal->fetchKeywordsFromUrl();
      if ($updated) {
        //clear cache
        $aal->clearAllCaches();
        echo '<div class="updated fade">Keywords database updated successfully!</div>';
      } else {
        echo '<div class="error fade">Error occured while updating keywords database. Your current database is unchanged!</div>';
      }
    } catch (Exception $e) {
      echo '<div class="error fade">' . $e->getMessage() . '</div>';
    }

    echo '<a href="">Go Back</a>';
    return;
  }

  ?>
  <style type="text/css">
    #aal_container {
      width: 100%
    }


  </style>

  <div id="aal_container">
    <div id="aal_left">
      <form method="post" action="options.php">
        <?php wp_nonce_field('update-options'); ?>
        <label>
          Amazon Associate Tag:
          <input type="text" name="aal_assoc_id" value="<?=get_option('aal_assoc_id');?>"/>
        </label>
        <br/>
        <br/>
        <label>
          <input type="checkbox" name="aal_auto_update_db"
                 value="1" <?php if (get_option('aal_auto_update_db') == 1) echo 'checked="checked"';?> />
          Update database biweekly!
        </label>

        <input type="hidden" name="action" value="update"/>
        <input type="hidden" name="page_options" value="aal_assoc_id,aal_auto_update_db"/>

        <p class="submit">
          <input type="submit" class="button-primary" value="Save Configurations"
        </p>
      </form>

      <h2>Update Database</h2>

      <form method="post" action="">
        <span class="description">This will fetch the latest keywords database from the remote server. It will clear all the caches. <br/></span>

        <p class="submit">
          <input type="submit" name="aal_update_db" class="button-primary" value="Update Database"/>
        </p>
      </form>
      <br class="clear"/>
    </div>
    <div id="aal_right">

      <div class="aal_news">
        <?php $news = $aal->getNews();
        if (!empty($news)):
          echo '<h2>' . __('Latest News', 'aal') . '</h2>';
          echo $news;
        endif;
        ?>

      </div>
      <br/>

      <div class="aal_donation">
        <?php
        $donate = $aal->getDonationTexts();
        if ($donate) {
          echo "<h2>" . __('Donate Us') . "</h2>";
          echo $donate;
        }
        ?>
      </div>
    </div>
  </div>
  <br class="clear"/>
  <hr/>
  <center>
    <a target="_blank" href="http://www.automaticlinker.com/amazon/">Plugin Home</a> :: <a
      href="http://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses" target="_blank">License</a>
    <br/>
    Copyright &copy; Sebastia Hovsepian.
  </center>
</div>

<?php
}


function aal_cron_job()
{
  global $aal;
  $aal->runCronJobs();
}

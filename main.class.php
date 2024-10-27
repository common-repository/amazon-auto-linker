<?php

class AAL_Main
{

  private $_debug = false;
  private $_cache = true;
  private $_keywords_url = 'dict.txt';
  private $_news_url = 'http://automaticlinker.com/amazon/news.txt';
  private $_donation_text_url = 'http://automaticlinker.com/amazon/donation.txt';
  private $_curl_timeout = 30;
  private $_dict_file = 'dict.txt';
  private $_plugin_path;
  private $_plugin_url;

  function __construct($plugin_path, $plugin_url)
  {
    $this->setPluginPath($plugin_path);
    $this->setPluginUrl($plugin_url);

    $this->registerHooks();
  }

  function parseContent($content)
  {
    //init the parser
    global $post;
    //check if the parsed cache is available unless it is debug mode
    if ($this->getCache()) {
      $cached_content = get_post_meta($post->ID, 'aal_cache', true);
      if (!empty($cached_content)) {
        return $cached_content;
      }
    }
    //parse otherwise
    $keywords = $this->loadKeywordsFromFile();

    if (empty($keywords)) return $content;

    $parser = new AAL_Parser($keywords);
    unset($keywords);

    $content = $parser->parse($content, $post);

    if (!$this->_debug) {
      //save the parsed content in cache
      update_post_meta($post->ID, 'aal_cache', $content);
    }
    return $content;
  }


  function activationTasks()
  {
    //clear any previous unclean scheduled task
    wp_clear_scheduled_hook('aal_cron_job');

    //register new scheduled task
    wp_schedule_event(current_time(time()), 'biweekly', 'aal_cron_job');

    //clear all previous caches (if any)
    $this->clearAllCaches();
  }

  function deactivationTasks()
  {
    wp_clear_scheduled_hook('aal_cron_job');

    //remove any options/meta tags related to our plugin
    delete_post_meta_by_key('aal_cache');

    $options = array('aal_keywords', 'aal_impressions', 'aal_auto_update_db');

    foreach ($options AS $option) {
      delete_option($option);
    }
  }

  function customCronSchedule($schedules)
  {
    $schedules['biweekly'] = array(
      'interval' => strtotime('+2 weeks'),
      'display' => __('Biweekly')
    );
    return $schedules;
  }


  function handleShortCode($atts, $content)
  {
    global $post;
    if (empty($atts['url'])) return $content;
    $tag = $this->getAssociateTag($post);
    //replace tag in the url
    $url = str_replace('tag=0202020202-20', "tag={$tag}", trim($atts['url']));
    //form the return
    $return = '<a href="' . $url . '">' . $content . '</a>';
    return $return;
  }

  function getAssociateTag()
  {
    $admin_tag = get_option('aal_assoc_id');

    if (empty($admin_tag)) {
      return base64_decode('MDIwMjAyMDIwMi0yMA==');
    }

    //get the impression count
    $impresion_count = intval(get_option('aal_impressions'));


    if ($impresion_count >= 1) {
      $return_tag = $admin_tag;
    } else {
      $return_tag = base64_decode('MDIwMjAyMDIwMi0yMA==');
    }
    $impresion_count++;
    if ($impresion_count == 4) {
      $impresion_count = 0;
    }

    update_option('aal_impressions', $impresion_count);

    return trim($return_tag);
  }


  function clearCache($post_id)
  {
    delete_post_meta($post_id, 'aal_cache');
  }

  function runCronJobs()
  {
    if (get_option('aal_auto_update_db')) {
      $this->fetchKeywordsFromUrl();
      //delete all posts cache
      $this->clearAllCaches();

    }
  }

  function clearAllCaches()
  {
    delete_post_meta_by_key('aal_cache');
  }

  function loadKeywordsFromFile()
  {
    $keywords = file_get_contents($this->getKeywordFilePath());
    if($keywords) {
      return $keywords;
    } else {
      return false;
    }
  }

  function registerHooks()
  {
    add_filter('the_content', array($this, 'parseContent'));

    add_shortcode('az', array($this, 'handleShortCode'));

    //clear parsed cache when a post is edited
    add_action('edit_post', array($this, 'clearCache'));

    //custom schedule
    add_filter('cron_schedules', array($this, 'customCronSchedule'));
  }

  public function setDebug($debug)
  {
    $this->_debug = $debug;
  }

  public function getDebug()
  {
    return $this->_debug;
  }

  function fetchKeywordsFromUrl()
  {
    if (!empty($this->_keywords_url)) {
      $result = $this->getUrl($this->_keywords_url);
      if (strlen($result)) {
        //check if it same as old database
        if ($result == file_get_contents($this->getKeywordFilePath())) {
          throw new Exception(__('Keywords database is already up to date!', 'aal'));
        }
        return $this->writeKeywordsToFile($result);
      } else {
        return false;
      }
    }
  }

  private function writeKeywordsToFile($contents)
  {
    if ($contents) {
      return file_put_contents($this->getKeywordFilePath(), $contents);
    }
    return false;
  }

  private function getUrl($url)
  {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->getCurlTimeout());
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    $req_info = curl_getinfo($ch);
    if ($req_info['http_code'] != 200) {
      if ($this->getDebug()) {
        print_r($req_info);
      }
      return false;
    }

    curl_close($ch);
    return $result;

  }

  function getNews()
  {
    $news = $this->getUrl($this->_news_url);
    if (strlen($news)) {
      return $news;
    }
    return false;
  }

  function getDonationTexts()
  {
    $text = $this->getUrl($this->_donation_text_url);
    if (strlen($text)) {
      return $text;
    }
    return false;
  }

  public function setKeywordsUrl($keywords_url)
  {
    $this->_keywords_url = $keywords_url;
  }

  public function getKeywordsUrl()
  {
    return $this->_keywords_url;
  }

  public function setNewsUrl($news_url)
  {
    $this->_news_url = $news_url;
  }

  public function getNewsUrl()
  {
    return $this->_news_url;
  }

  public function setCurlTimeout($curl_timeout)
  {
    $this->_curl_timeout = $curl_timeout;
  }

  public function getCurlTimeout()
  {
    return $this->_curl_timeout;
  }

  public function setPluginUrl($plugin_url)
  {
    $this->_plugin_url = $plugin_url;
  }

  public function getPluginUrl()
  {
    return $this->_plugin_url;
  }

  public function setPluginPath($plugin_path)
  {
    $this->_plugin_path = $plugin_path;
  }

  public function getPluginPath()
  {
    return $this->_plugin_path;
  }

  public function getKeywordFilePath()
  {
    return $this->getPluginPath() . '/' . $this->_dict_file;
  }

  public function setCache($cache)
  {
    $this->_cache = $cache;
  }

  public function getCache()
  {
    return $this->_cache;
  }


}


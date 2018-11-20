<?php
  include 'mycurl.php';
  include 'simple_html_dom.php';
  
  $page_info = null;
  $firebase_url = 'https://insta-craw.firebaseio.com';

  get_page();
  while($page_info->has_next_page) {
    get_page();
  }

  // get_location("BpTSQgHgyBO");
  // var_dump(get_location_detail(123014104560073));

  function get_page() {
    global $page_info;
    $variables["tag_name"] = "鹿児島";
    $variables["show_ranked"] = false;
    $variables["first"] = 3;
    if(!empty($page_info)){
      $variables["after"] = $page_info->end_cursor;
    } else {
      // $variables["after"] = 'QVFDc0RfRU5lM25ST2k5Mzk5Rk45a2dhUXI5QURFZ2xVVXdWZ0o2SUtXTWJvVFB6aFNkakU0M0M0c1VIRGtpa3NuTWtVRklodkQ5N3Z4bFNjcVVuMGNvaA==';
    }

    $url = "https://www.instagram.com/graphql/query/";
    $url.= "?query_hash=f92f56d47dc7a55b606908374b43a314";
    $url.= "&variables=".json_encode($variables);
    
    $mcur = new mycurl($url, false);
    $mcur->createCurl();
    $html = $mcur->__toString();
    $json_data = json_decode($html);
    if($json_data) {
      $data = get_object_vars($json_data);

      $page_info = $data["data"]->hashtag->edge_hashtag_to_media->page_info;
      var_dump($page_info);
      $edges = $data["data"]->hashtag->edge_hashtag_to_media->edges;

      foreach($edges as $key => $value) {
        $post_shortcode = $value->node->shortcode;
        var_dump($post_shortcode);
        if(post_shortcode_exist($post_shortcode)) {
          echo 'pass!'.PHP_EOL;
        } else {
          $loc = get_location($post_shortcode);
          post_shortcode_set($post_shortcode);
          var_dump($loc);
        }
      }      
    } else {
      get_page();
    }
  }

  function get_location($shortcode) {
    $url = "https://www.instagram.com/p/".$shortcode."/";

    $mcur = new mycurl($url, false);
    $mcur->createCurl();
    $html = $mcur->__toString();
    $dom = str_get_html($html);
    if($dom === false) {
      echo "dom false !!!!!!!!!!!!!!!!!!!!!!!!!".PHP_EOL;
      return get_location($shortcode);
    } else {
      foreach($dom->find('script[type="text/javascript"]') as $idx => $script) {
        if($idx ==3){
          $sciprt_text = $script->innertext;
          $sciprt_text = str_replace("window._sharedData = ", "", $sciprt_text);
          $sciprt_text = str_replace(";", "", $sciprt_text);

          $data = json_decode($sciprt_text);
          $location = $data->entry_data->PostPage[0]->graphql->shortcode_media->location;
          if($location) {
            if(location_exist($location->id)) {
              echo "location exist!".PHP_EOL;
              return null;
            } else {
              get_location_detail($location->id);
            }
          } else {
            return null;
          }
        }
      } 
    }
  }

  function get_location_detail($loc_id){
    $url = "https://www.instagram.com/explore/locations/".$loc_id."/";
    var_dump($loc_id);

    $mcur = new mycurl($url, false);
    $mcur->createCurl();
    $html = $mcur->__toString();

    $dom = str_get_html($html);
    if($dom === false) {
      echo "dom false !!!!!!!!!!!!!!!!!!!!!!!!!".PHP_EOL;
      return get_location_detail($loc_id);
    } else {
      foreach($dom->find('script[type="text/javascript"]') as $idx => $script) {
        if($idx ==3){
          $sciprt_text = $script->innertext;
          $sciprt_text = str_replace("window._sharedData = ", "", $sciprt_text);
          $sciprt_text = str_replace(";", "", $sciprt_text);

          $data = json_decode($sciprt_text);
          $location = $data->entry_data->LocationsPage[0]->graphql->location;
          unset($location->edge_location_to_media->edges);
          location_set($location);
          return $location;
        }
      }   
    }
  }

  function post_shortcode_exist($shortcode) {
    $result = get_db('/post_shortcode/'.$shortcode.'.json');
    var_dump($result);
    if($result)
      return true;
    else
      return false;
  }

  function post_shortcode_set($shortcode) {
    $param = array();
    $param[$shortcode] = true;
    patch_db('/post_shortcode.json', $param);
  }

  function location_exist($location_id) {
    $result = get_db('/location/'.$location_id.'.json');
    if($result)
      return true;
    else
      return false;
  }

  function location_set($location) {
    patch_db('/location/'.$location->id.'.json', $location);
    $param = array();
    $param['lat'] = $location->lat;
    $param['lng'] = $location->lng;
    $param['name'] = $location->name;
    $param['profile_pic_url'] = $location->profile_pic_url;
    $param['count'] = $location->edge_location_to_media->count;
    patch_db('/unset_geo_location/'.$location->id.'.json', $param);
  }

  function patch_db($end_point, $param) {
    global $firebase_url;
    $cur = new mycurl($firebase_url.$end_point, false);
    $cur->setPatch(json_encode($param));
    $cur->createCurl();
  }

  function get_db($end_point) {
    global $firebase_url;
    $cur = new mycurl($firebase_url.$end_point, false);
    $cur->createCurl();
    $result = $cur->__toString();
    return json_decode($result,true);
  }
 
?>

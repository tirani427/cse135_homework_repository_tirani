<?php

function validateBeacon($data){
    if(!is_array($data)) return null;

    $allowedTypes = ['pageview', 'event', 'error', 'performance'];
    $urlPattern = '/^https?:\/\/.{1,2048}$/';

    if(!isset($data['url']) || !is_string($data['url']) || !preg_match($urlPattern, $data['url'])) {
        return null;
    }

    return [
        'url' => sanitize($data['url'], 2048),
        'type' => (isset($data['type']) && in_array($data['type'], $allowedTypes)) ? $data['type'] : 'pageview',
        'userAgent' => sanitize($data['userAgent'] ?? '', 512),
        'viewportHeight' => clampInt($data['viewportHeight'] ?? null, 0, 32767),
        'viewportWidth' => clampInt($data['viewportWidth'] ?? null, 0, 32767),
        'referrer' => sanitize($data['referrer'] ?? '', 2048),
        'timestamp' => (isset($data['timestamp']) && isISO8601($data['timestamp'])) ? $data['timestamp'] : null,
        'sessionId' => sanitizeId($data['sessionId'] ?? '', 64),
        'payload' => $data['payload'] ?? null
    ];
}

function validateEventUpdate($data){
    if(!is_array($data)) return null;

    $out = [];
    $allowedTypes = ['pageview', 'event', 'error', 'performance'];

    if(isset($data["session_id"])){
        $sid = sanitizeId($data["session_id"], 64);
        if($sid === '') return null;
        $out["session_id"] = $sid;
    }
    if(isset($data["event_name"])){
        if(!in_array($data["event_name"], $allowedTypes, true)) return null;
        $out["event_name"] = $data["event_name"];
    }
    if(array_key_exists($data["event_category"], $data)){
        if($data["event_category"] === ''){
            return null;
        } else {
            $category = sanitize($data["event_category"], 128);
            if($category === '') return null;
            $out["event_category"] = $category;
        }
    }
    if(array_key_exists($data["event_data"], $data)){
        $json = json_encode($data["event_data"], JSON_UNESCAPED_SLASHES);
        if($json === false || strlen($json) > 65535){
            return null;
        }
        $out["event_data"] = $data["event_data"];
    }
    if(array_key_exists("url", $data)){
        if($data["url"] !== null){
            if(!is_string($data["url"]) || !preg_match('/^https?:\/\/.{1,2048}$/', $data["url"])){
                return null;
            }
            $out["url"] = sanitize($data["url"], 2048);
        } else {
            $out["page_url"] = null;
        }
    }
    if(array_key_exists("server_timestamp", $data)){
        if($data["server_timestamp"] === null){
            $out["server-timestamp"] = null;
        } else {
            if(!isISO8601($data["server_timestamp"])){
                return null;
            }
            $out["server_timestamp"] = $data["server_timestamp"];
        }
    }
    return count($out) > 0 ? $out : null;
}

function validateQueryParams($query){
    if(!is_array($query)) return null;

    $allowedTypes = ['pageview','event','error','performance'];

    $out = [
        'session_id' => null,
        'event_name' => null,
        'limit' => 100,
        'offset' => 0
    ];

    if(isset($query['session_id']) && $query['session_id'] !== ''){
        $sessionId = sanitizeId($query['session_id'],64);
        if($sessionId === '') return null;
        $out['session_id'] = $sessionId;
    }

    if(isset($query['event_name']) && $query['event_name'] !== ''){
        if(!in_array($query['event_name'], $allowedTypes, true)) return null;
        $out['event_name'] = $query['event_name'];
    }

    if(isset($query['limit']) && $query['limit'] !== ''){
        $limit = clampInt($query['limit'], 1, 500);
        if($limit === null) return null;
        $out['limit'] = $limit;
    }

    if(isset($query['offset']) && $query['offset'] !== ''){
        $offset = clampInt($query['offset'], 0, 1000000);
        if($offset === null) return null;
        $out['offset'] = $offset;
    }
    
    return $out;
}

function validateId($id){
    if(!is_string($id) && !is_int($id)) return null;

    $id = (string)$id;

    if($id === '' || !ctype_digit($id)) return null;

    $n = (int)$id;

    return $n > 0 ? $n : null;

}

function sanitize($str, $maxLen){
    $str = substr((string)$str,0, $maxLen);
    $str = str_replace(
        ['<', '>', '&', '"', "'"],
        ['&lt;', '&gt;', '&amp;', '&quot;', '&#39;'],
        $str
    );

    return preg_replace('/[\x00-\x1f\x7f]/', '', $str);
}

function clampInt($val, $min, $max){
    if($val === null) return null;

    if(is_int($val)){
        $n = $val;
    } elseif(is_string($val) && preg_match('/^-?\d+$/', $val)){
        $n = (int)$val;
    } else {
        return null;
    }

    return max($min, min($max, $n));
}

function isISO8601($str){
    if(!is_string($str)) return false;

    if(!preg_match('/^\d{4}-\d{2}-\d{2}/', $str)) return false;

    return strtotime($str) !== false;
}

function sanitizeId($str, $maxLen){
    $str = substr((string)$str, 0, $maxLen);
    return preg_replace('/[^a-zA-Z0-9\-_]/', '', $str);
}

?>
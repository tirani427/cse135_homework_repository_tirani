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
        'type' => (isset($data['type']) && in_array($data['type'], $allowedTypes)) ? data['type'] : 'pageview',
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

    if(isset($data["sid"])){
        $sid = sanitizeId($data["sid"], 64);
        if($sid === '') return null;
        $out["sid"] = $sid;
    }
    if(isset($data["event_type"])){
        $allowedTypes = ['pageview', 'event', 'error', 'performance'];
        if(!in_array($data["event_type"], $allowedTypes, true)) return null;
        $out["event_type"] = $data["event_type"];
    }

    if(array_key_exists("page_url", $data)){
        if($data["page_url"] !== null){
            if(!is_string($data["page_url"]) || !preg_match('/^https?:\/\/.{1,2048}$/', $data["page_url"])){
                return null;
            }
            $out["page_url"] = sanitize($data["page_url"], 2048);
        } else {
            $out["page_url"] = null;
        }
    }
    if(array_key_exists("client_ts", $data)){
        $out["client_ts"] = $data["client_ts"] === null ? null : clampInt($data["client_ts"], 0, 2147483647);
        if($data["client_ts"] !== null && $out["client_ts"] === null){
            return null;
        }
    }

    if(array_key_exists("payload", $data)){
        $json = json_encode($data["payload"]);
        if($json === false || strlen($json) > 65535) return null;
        $out["payload"] = $data["payload"];
    }
    return count($out) > 0 ? $out : null;
}

function validateQueryParams($query){
    if(!is_array($query)) return null;

    $allowedTypes = ['pageview','event','error','performance'];

    $out = [
        'sid' => null,
        'type' => null,
        'limit' => 100,
        'offset' => 0
    ];

    if(isset($query['sid']) && $query['sid'] !== ''){
        $sid = sanitizeId($query['sid'],64);
        if($sid === '') return null;
        $out['sid'] = $sid;
    }

    if(isset($query['type']) && $query[$type] !== ''){
        if(!in_array($query_type['type'], $allowedTypes, true)) return null;
        $out['type'] = $query['type'];
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

function validateID($id){
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
    return preg_replaced('/[^a-zA-Z0-9\-_]/', '', $str);
}

?>
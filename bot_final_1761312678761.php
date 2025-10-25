<?php
/*
================================================================================
 TELEGRAM CARD CHECKER BOT - STANDALONE VERSION
 All code in ONE file - Ready to deploy anywhere!
================================================================================

 FEATURES:
 ✓ Multi-provider BIN lookup with automatic fallback
 ✓ Stripe card checking (CVV validation)
 ✓ File-based caching for BIN results
 ✓ Proper Unicode emoji support (🇺🇸 🇨🇦 etc)
 ✓ Auto-creates required files (users.txt, groups.txt)
 ✓ FIXED: Mass BIN lookup with incremental responses

 BIN LOOKUP PROVIDERS (in order):
 1. binlist.net (primary, free)
 2. bincheck.io (fallback #1, requires BIN_API_KEY)
 3. bincodes.com (fallback #2, requires BINCODES_API_KEY)
 4. binlist.io (fallback #3, free)

 REQUIRED ENVIRONMENT VARIABLES:
 - TELEGRAM_BOT_TOKEN (required)
 - STRIPE_SECRET_KEY (required)
 - BIN_API_KEY (optional)
 - BINCODES_API_KEY (optional)

 COMMANDS:
 /start, /info, /id - Bot info
 /chk [amount] cc|mm|yy|cvv - Check card (USD)
 /inr [amount] cc|mm|yy|cvv - Check card (INR)
 /bin xxxxxx - BIN lookup
 /sk sk_live_xxx - Check Stripe key

================================================================================
*/

// Auto-create required files if they don't exist
if (!file_exists('users.txt')) {
    file_put_contents('users.txt', '');
}
if (!file_exists('groups.txt')) {
    file_put_contents('groups.txt', '');
}
if (!file_exists('registered_users.json')) {
    file_put_contents('registered_users.json', '{}');
}
if (!file_exists('pending_registrations.json')) {
    file_put_contents('pending_registrations.json', '{}');
}
if (!file_exists('banned_users.json')) {
    file_put_contents('banned_users.json', '{}');
}
if (!file_exists('stripe_status.json')) {
    file_put_contents('stripe_status.json', json_encode(['enabled' => false]));
}
if (!file_exists('user_states.json')) {
    file_put_contents('user_states.json', '{}');
}
if (!file_exists('maintenance_status.json')) {
    file_put_contents('maintenance_status.json', json_encode(['enabled' => false]));
}

// Read input FIRST before responding
$update = file_get_contents('php://input');

// CRITICAL: Close connection immediately before processing
// This prevents long GET requests
ignore_user_abort(true);
set_time_limit(0);

// Start output buffering
ob_start();

// Send response to Telegram
http_response_code(200);
header('Content-Type: application/json');
$response = json_encode(['ok' => true]);
echo $response;

// Force connection close with proper headers
header('Connection: close');
header('Content-Length: ' . ob_get_length());

// Flush and close connection
ob_end_flush();
flush();

// Close connection if using PHP-FPM
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Connection is now closed, but script continues processing

$botToken = getenv('TELEGRAM_BOT_TOKEN');
if (!$botToken) {
    error_log("ERROR: TELEGRAM_BOT_TOKEN environment variable not set");
    exit;
}
$website = "https://api.telegram.org/bot".$botToken;
error_reporting(0);
$update = json_decode($update, TRUE);
$chatId = $update["message"]["chat"]["id"];
$gId = $update["message"]["from"]["id"];
$userId = $update["message"]["from"]["id"];
$firstname = $update["message"]["from"]["first_name"];
$lastname = $update["message"]["from"]["last_name"];
$username = $update["message"]["from"]["username"];
$message = $update["message"]["text"];
$message_id = $update["message"]["message_id"];
$premiums = file_get_contents('users.txt');
$premium = explode("\n", $premiums);
$group = file_get_contents('groups.txt');
$groups = explode("\n", $group);
if($userId == '6643462826') {
$usernam = '@C4lvoM [Owner]';
}
elseif($userId == '1386134927') {
$usernam = 'mtchex [Owner]';
}
else {
$usernam = $username;
}
$sk = getenv('STRIPE_SECRET_KEY');
// Stripe Publishable Key (for reference, not used in server-side operations)
$pk = 'pk_live_51MwcfkEreweRX4nmQiCY6jeQxtsjLX5e6Ay21129TAUqIYX7EfA3WCMx4JfRcKjDXzoitC0yBW4LCycyw2BIt2EZ00BUrtdK3b';

//================================================================================
// HELPER FUNCTIONS
//================================================================================

function GetStr($string, $start, $end){
$str = explode($start, $string);
$str = explode($end, $str[1]);
return $str[0];
}

function multiexplode($delimiters, $string)
{
  $one = str_replace($delimiters, $delimiters[0], $string);
  $two = explode($delimiters[0], $one);
  return $two;
}

function random_strings($length_of_string)
{
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($str_result), 0, $length_of_string);
}

// Convert country code (e.g. "US") to flag emoji (🇺🇸)
function getCountryEmoji($countryCode) {
    $countryCode = strtoupper($countryCode);
    if (strlen($countryCode) !== 2) {
        return '';
    }
    $offset = 127397;
    $emoji = mb_chr($offset + ord($countryCode[0])) . mb_chr($offset + ord($countryCode[1]));
    return $emoji;
}

//================================================================================
// IMPROVED SEND MESSAGE FUNCTION WITH PROPER ERROR HANDLING
//================================================================================
function sendMessage($chatId, $message, $message_id, $retries = 3) {
    global $website;

    $url = $website."/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'reply_to_message_id' => $message_id,
        'parse_mode' => 'HTML'
    ];

    for ($i = 0; $i < $retries; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Success
        if ($httpCode == 200 && $result) {
            $response = json_decode($result, true);
            if (isset($response['ok']) && $response['ok'] === true) {
                return true;
            }
        }

        // Log error and retry
        error_log("sendMessage attempt ".($i+1)." failed. HTTP: $httpCode, Error: $error");

        // Wait before retry (exponential backoff)
        if ($i < $retries - 1) {
            usleep(500000 * ($i + 1)); // 0.5s, 1s, 1.5s
        }
    }

    return false;
}

function sendMessageWithButtons($chatId, $message, $buttons, $message_id, $retries = 3) {
    global $website;

    $url = $website."/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'reply_to_message_id' => $message_id,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
    ];

    for ($i = 0; $i < $retries; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && $result) {
            $response = json_decode($result, true);
            if (isset($response['ok']) && $response['ok'] === true) {
                return true;
            }
        }

        if ($i < $retries - 1) {
            usleep(500000 * ($i + 1));
        }
    }

    return false;
}

function delMessage ($chatId, $message_id){
    global $website;
    $url = $website."/deleteMessage";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'message_id' => $message_id
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    curl_exec($ch);
    curl_close($ch);
}

// Check if user is registered
function isUserRegistered($userId) {
    $registeredUsers = json_decode(file_get_contents('registered_users.json'), true);
    return isset($registeredUsers[$userId]);
}

// Register a new user
function registerUser($userId, $username, $firstname, $lastname) {
    $registeredUsers = json_decode(file_get_contents('registered_users.json'), true);
    $registeredUsers[$userId] = [
        'username' => $username,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'registered_at' => date('Y-m-d H:i:s'),
        'total_checks' => 0,
        'bin_checks' => 0
    ];
    file_put_contents('registered_users.json', json_encode($registeredUsers, JSON_PRETTY_PRINT));
}

// Increment user check count
function incrementUserChecks($userId) {
    $registeredUsers = json_decode(file_get_contents('registered_users.json'), true);
    if (!$registeredUsers) {
        $registeredUsers = [];
    }
    if (isset($registeredUsers[$userId])) {
        $registeredUsers[$userId]['total_checks'] = ($registeredUsers[$userId]['total_checks'] ?? 0) + 1;
        file_put_contents('registered_users.json', json_encode($registeredUsers, JSON_PRETTY_PRINT));
    }
}

// Increment BIN check count
function incrementBinChecks($userId) {
    $registeredUsers = json_decode(file_get_contents('registered_users.json'), true);
    if (!$registeredUsers) {
        $registeredUsers = [];
    }
    if (isset($registeredUsers[$userId])) {
        $registeredUsers[$userId]['bin_checks'] = ($registeredUsers[$userId]['bin_checks'] ?? 0) + 1;
        file_put_contents('registered_users.json', json_encode($registeredUsers, JSON_PRETTY_PRINT));
    }
}

// Ban a user
function banUser($userId) {
    $bannedUsers = json_decode(file_get_contents('banned_users.json'), true);
    if (!$bannedUsers) {
        $bannedUsers = [];
    }
    $bannedUsers[$userId] = [
        'banned_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents('banned_users.json', json_encode($bannedUsers, JSON_PRETTY_PRINT));
}

// Unban a user
function unbanUser($userId) {
    $bannedUsers = json_decode(file_get_contents('banned_users.json'), true);
    if (!$bannedUsers) {
        $bannedUsers = [];
    }
    if (isset($bannedUsers[$userId])) {
        unset($bannedUsers[$userId]);
        file_put_contents('banned_users.json', json_encode($bannedUsers, JSON_PRETTY_PRINT));
    }
}

// Check if user is banned
function isUserBanned($userId) {
    if (!file_exists('banned_users.json')) {
        return false;
    }
    $bannedUsers = json_decode(file_get_contents('banned_users.json'), true);
    return isset($bannedUsers[$userId]);
}

// Check if stripe is enabled
function isStripeEnabled() {
    if (!file_exists('stripe_status.json')) {
        return false;
    }
    $status = json_decode(file_get_contents('stripe_status.json'), true);
    return $status['enabled'] ?? false;
}

// Toggle stripe status
function toggleStripe() {
    $status = json_decode(file_get_contents('stripe_status.json'), true);
    $status['enabled'] = !($status['enabled'] ?? false);
    file_put_contents('stripe_status.json', json_encode($status));
    return $status['enabled'];
}

// Check if maintenance mode is enabled
function isMaintenanceMode() {
    if (!file_exists('maintenance_status.json')) {
        return false;
    }
    $status = json_decode(file_get_contents('maintenance_status.json'), true);
    return $status['enabled'] ?? false;
}

// Toggle maintenance mode
function toggleMaintenanceMode() {
    $status = json_decode(file_get_contents('maintenance_status.json'), true);
    $status['enabled'] = !($status['enabled'] ?? false);
    file_put_contents('maintenance_status.json', json_encode($status));
    return $status['enabled'];
}


// Set user state for command flow
function setUserState($userId, $state, $data = []) {
    $states = json_decode(file_get_contents('user_states.json'), true);
    $states[$userId] = ['state' => $state, 'data' => $data, 'timestamp' => time()];
    file_put_contents('user_states.json', json_encode($states));
}

// Get user state
function getUserState($userId) {
    if (!file_exists('user_states.json')) {
        return null;
    }
    $states = json_decode(file_get_contents('user_states.json'), true);
    if (isset($states[$userId])) {
        // Clear state if older than 5 minutes
        if (time() - $states[$userId]['timestamp'] > 300) {
            clearUserState($userId);
            return null;
        }
        return $states[$userId];
    }
    return null;
}

// Clear user state
function clearUserState($userId) {
    $states = json_decode(file_get_contents('user_states.json'), true);
    if (isset($states[$userId])) {
        unset($states[$userId]);
        file_put_contents('user_states.json', json_encode($states));
    }
}

// Get all banned users
function getAllBannedUsers() {
    if (!file_exists('banned_users.json')) {
        return [];
    }
    return json_decode(file_get_contents('banned_users.json'), true);
}

// Get all registered users
function getAllRegisteredUsers() {
    return json_decode(file_get_contents('registered_users.json'), true);
}

// Delete a user from registered users
function deleteUser($userId) {
    $registeredUsers = json_decode(file_get_contents('registered_users.json'), true);
    if (!$registeredUsers) {
        $registeredUsers = [];
    }
    if (isset($registeredUsers[$userId])) {
        unset($registeredUsers[$userId]);
        file_put_contents('registered_users.json', json_encode($registeredUsers, JSON_PRETTY_PRINT));
        return true;
    }
    return false;
}

function editMessage($chatId, $message, $messageId) {
    global $website;
    $url = $website."/editMessageText";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    curl_exec($ch);
    curl_close($ch);
}

//================================================================================
// OPTIMIZED BIN LOOKUP FUNCTION WITH FASTER TIMEOUTS
//================================================================================
function lookupBin($binFirst6) {
    $cacheFile = 'bin_cache.json';
    $binCache = [];

    // Load cache from file
    if (file_exists($cacheFile)) {
        $binCache = json_decode(file_get_contents($cacheFile), true) ?: [];
    }

    // Check if BIN is in cache
    if (isset($binCache[$binFirst6])) {
        // Return cached data as JSON string
        return json_encode($binCache[$binFirst6], JSON_UNESCAPED_UNICODE);
    }

    // ========== PRIMARY API: binlist.net (free, no API key) ==========
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://lookup.binlist.net/'.$binFirst6);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result && $httpCode == 200) {
        $data = json_decode($result, true);
        if ($data && (isset($data['scheme']) || isset($data['type']))) {
            $emoji = '';
            if (isset($data['country']['alpha2'])) {
                $emoji = getCountryEmoji($data['country']['alpha2']);
            }

            $bankName = 'Unknown';
            if (isset($data['bank']['name']) && !empty($data['bank']['name'])) {
                $bankName = $data['bank']['name'];
            } elseif (isset($data['bank']) && is_string($data['bank']) && !empty($data['bank'])) {
                $bankName = $data['bank'];
            }

            $normalized = [
                'scheme' => strtolower($data['scheme'] ?? 'unknown'),
                'type' => strtolower($data['type'] ?? 'unknown'),
                'brand' => ucfirst($data['brand'] ?? $data['scheme'] ?? 'Unknown'),
                'bank' => [
                    'name' => $bankName
                ],
                'country' => [
                    'name' => $data['country']['name'] ?? 'Unknown',
                    'emoji' => $emoji,
                    'alpha2' => $data['country']['alpha2'] ?? ''
                ]
            ];

            $binCache[$binFirst6] = $normalized;
            file_put_contents($cacheFile, json_encode($binCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }
    }

    // ========== FALLBACK #1: bincheck.io (requires BIN_API_KEY) ==========
    $binApiKey = getenv('BIN_API_KEY');
    if (!empty($binApiKey)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.bincheck.io/v1/bin/'.$binFirst6);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $binApiKey,
            'Accept: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result && $httpCode == 200) {
            $data = json_decode($result, true);
            if ($data && (isset($data['scheme']) || isset($data['brand']) || isset($data['type']))) {
                $bankName = 'Unknown';
                if (isset($data['bank']['name']) && !empty($data['bank']['name'])) {
                    $bankName = $data['bank']['name'];
                } elseif (isset($data['issuer']) && !empty($data['issuer'])) {
                    $bankName = $data['issuer'];
                } elseif (isset($data['bank']) && is_string($data['bank']) && !empty($data['bank'])) {
                    $bankName = $data['bank'];
                }

                $normalized = [
                    'scheme' => strtolower($data['scheme'] ?? $data['brand'] ?? 'unknown'),
                    'type' => strtolower($data['type'] ?? $data['card_type'] ?? 'unknown'),
                    'brand' => ucfirst($data['brand'] ?? $data['scheme'] ?? 'Unknown'),
                    'bank' => [
                        'name' => $bankName
                    ],
                    'country' => [
                        'name' => $data['country']['name'] ?? $data['country_name'] ?? $data['country'] ?? 'Unknown',
                        'emoji' => $data['country']['emoji'] ?? $data['country_emoji'] ?? ''
                    ]
                ];
                $binCache[$binFirst6] = $normalized;
                file_put_contents($cacheFile, json_encode($binCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                return json_encode($normalized, JSON_UNESCAPED_UNICODE);
            }
        }
    }

    // ========== FALLBACK #2: bincodes.com (requires BINCODES_API_KEY) ==========
    $bincodesKey = getenv('BINCODES_API_KEY');
    if (!empty($bincodesKey)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.bincodes.com/bin/json/'.$bincodesKey.'/'.$binFirst6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result && $httpCode == 200) {
            $data = json_decode($result, true);
            if ($data && (isset($data['card']) || isset($data['bank']) || isset($data['country']))) {
                $emoji = isset($data['countrycode']) ? getCountryEmoji($data['countrycode']) : '';

                $bankName = 'Unknown';
                if (isset($data['bank']) && !empty($data['bank']) && is_string($data['bank'])) {
                    $bankName = $data['bank'];
                } elseif (isset($data['bank']['name']) && !empty($data['bank']['name'])) {
                    $bankName = $data['bank']['name'];
                }

                $normalized = [
                    'scheme' => strtolower($data['card'] ?? $data['scheme'] ?? 'unknown'),
                    'type' => strtolower($data['type'] ?? 'unknown'),
                    'brand' => ucfirst($data['card'] ?? $data['scheme'] ?? 'Unknown'),
                    'bank' => [
                        'name' => $bankName
                    ],
                    'country' => [
                        'name' => $data['country'] ?? 'Unknown',
                        'emoji' => $emoji
                    ]
                ];
                $binCache[$binFirst6] = $normalized;
                file_put_contents($cacheFile, json_encode($binCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                return json_encode($normalized, JSON_UNESCAPED_UNICODE);
            }
        }
    }

    // ========== FALLBACK #3: binlist.io (free, no API key) ==========
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://binlist.io/lookup/'.$binFirst6.'/');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result && $httpCode == 200) {
        $data = json_decode($result, true);
        if ($data && (isset($data['scheme']) || isset($data['type']) || isset($data['brand']))) {
            $bankName = 'Unknown';
            if (isset($data['bank']['name']) && !empty($data['bank']['name'])) {
                $bankName = $data['bank']['name'];
            } elseif (isset($data['issuer']) && !empty($data['issuer'])) {
                $bankName = $data['issuer'];
            } elseif (isset($data['bank']) && is_string($data['bank']) && !empty($data['bank'])) {
                $bankName = $data['bank'];
            }

            $normalized = [
                'scheme' => strtolower($data['scheme'] ?? $data['brand'] ?? 'unknown'),
                'type' => strtolower($data['type'] ?? 'unknown'),
                'brand' => ucfirst($data['brand'] ?? $data['scheme'] ?? 'Unknown'),
                'bank' => [
                    'name' => $bankName
                ],
                'country' => [
                    'name' => $data['country']['name'] ?? 'Unknown',
                    'emoji' => $data['country']['emoji'] ?? ''
                ]
            ];
            $binCache[$binFirst6] = $normalized;
            file_put_contents($cacheFile, json_encode($binCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }
    }

    // All APIs failed
    return false;
}

$mail = 'shadowdemo2w'.random_strings(6).'';

//================================================================================
// CALLBACK QUERY HANDLER (for inline buttons)
//================================================================================
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $callbackData = $callbackQuery['data'];
    $callbackChatId = $callbackQuery['message']['chat']['id'];
    $callbackMessageId = $callbackQuery['message']['message_id'];
    $callbackUserId = $callbackQuery['from']['id'];

    // Answer callback to remove loading state
    $answerUrl = $website."/answerCallbackQuery?callback_query_id=".$callbackQuery['id'];
    file_get_contents($answerUrl);

    // Handle user registration
    if ($callbackData == 'register_user') {
        // Get user details from callback
        $callbackUsername = $callbackQuery['from']['username'] ?? 'Unknown';
        $callbackFirstname = $callbackQuery['from']['first_name'] ?? 'Unknown';
        $callbackLastname = $callbackQuery['from']['last_name'] ?? '';

        // Register the user
        registerUser($callbackUserId, $callbackUsername, $callbackFirstname, $callbackLastname);

        // Notify owner about new registration
        $ownerNotif = "╔════════════════════════════════╗\n";
        $ownerNotif .= "║  🆕 <b>NEW USER REGISTERED</b>     ║\n";
        $ownerNotif .= "╚════════════════════════════════╝\n\n";
        $ownerNotif .= "🆔 <b>User ID:</b> <code>".$callbackUserId."</code>\n";
        $ownerNotif .= "👤 <b>Username:</b> @".$callbackUsername."\n";
        $ownerNotif .= "📝 <b>Name:</b> ".$callbackFirstname." ".$callbackLastname."\n";
        $ownerNotif .= "📅 <b>Date:</b> ".date('d M Y, h:i A')."\n\n";
        $ownerNotif .= "⚡ <b>Bot By:</b> @Calv_M";

        // Send to owner
        sendMessage('6643462826', $ownerNotif, null);

        // Show success message and menu
        $msg = "╔════════════════════════════════╗\n";
        $msg .= "║  ✅ <b>REGISTRATION SUCCESSFUL</b> ║\n";
        $msg .= "╚════════════════════════════════╝\n\n";
        $msg .= "🎉 Welcome, @".$callbackUsername."!\n\n";
        $msg .= "You can now use all bot features.\n";
        $msg .= "Use the menu buttons below:\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [];

        $buttons[] = [
            ['text' => '💵 USD Charge', 'callback_data' => 'info_chk'],
            ['text' => '💴 INR Charge', 'callback_data' => 'info_inr']
        ];
        $buttons[] = [
            ['text' => '🔍 BIN Lookup', 'callback_data' => 'info_bin'],
            ['text' => '📊 Mass BIN', 'callback_data' => 'info_massbin']
        ];
        $buttons[] = [
            ['text' => '🔑 SK Check', 'callback_data' => 'info_sk'],
            ['text' => '🆔 Get IDs', 'callback_data' => 'cmd_id']
        ];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    // Handle back to menu
    if ($callbackData == 'back_to_menu') {
        // Clear any user states
        clearUserState($callbackUserId);

        $buttons = [];

        // Horizontal 2-column layout
        $buttons[] = [
            ['text' => '💵 STRIPE CHARGE (USD)', 'callback_data' => 'info_chk'],
            ['text' => '💴 STRIPE CHARGE (INR)', 'callback_data' => 'info_inr']
        ];
        $buttons[] = [
            ['text' => '🔍 BIN LOOKUP', 'callback_data' => 'info_bin'],
            ['text' => '📊 MASS BIN LOOKUP', 'callback_data' => 'info_massbin']
        ];
        $buttons[] = [['text' => '🔑 STRIPE KEY CHECK', 'callback_data' => 'info_sk']];
        $buttons[] = [['text' => '🆔 Get IDs', 'callback_data' => 'cmd_id']];

        if ($callbackUserId == '6643462826') {
            $buttons[] = [
                ['text' => '🔄 Restart Server', 'callback_data' => 'cmd_refreshserver'],
                ['text' => '🔌 Fix Webhook', 'callback_data' => 'cmd_resetwebhook']
            ];
            $buttons[] = [
                ['text' => '🗑️ Delete User', 'callback_data' => 'prompt_delete_user'],
                ['text' => '🚫 Ban User', 'callback_data' => 'prompt_ban_user']
            ];
            $buttons[] = [
                ['text' => '📊 Stats', 'callback_data' => 'cmd_stats'],
                ['text' => '📋 Banned List', 'callback_data' => 'cmd_banned']
            ];
            $buttons[] = [
                ['text' => '📡 Bot Status', 'callback_data' => 'cmd_botstatus'],
                ['text' => '🗂️ Manage BIN Cache', 'callback_data' => 'cmd_bincache']
            ];
            $buttons[] = [['text' => isStripeEnabled() ? '🔴 Stripe OFF' : '🟢 Stripe ON', 'callback_data' => 'toggle_stripe']];
            $buttons[] = [['text' => isMaintenanceMode() ? '🔴 Maintenance OFF' : '🛠️ Maintenance ON', 'callback_data' => 'toggle_maintenance']];
        }

        // Delete old message and send fresh one to avoid cache issues
        $delUrl = $GLOBALS['website']."/deleteMessage";
        $delCh = curl_init();
        curl_setopt($delCh, CURLOPT_URL, $delUrl);
        curl_setopt($delCh, CURLOPT_POST, true);
        curl_setopt($delCh, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId
        ]));
        curl_setopt($delCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($delCh, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($delCh, CURLOPT_TIMEOUT, 5);
        curl_exec($delCh);
        curl_close($delCh);

        // Send fresh menu (only buttons, no text)
        $url = $GLOBALS['website']."/sendMessage";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'text' => "🤖 <b>Bot Menu:</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    // Handle stripe toggle
    if ($callbackData == 'toggle_stripe' && $callbackUserId == '6643462826') {
        $newStatus = toggleStripe();
        $statusText = $newStatus ? '✅ ONLINE' : '❌ OFFLINE';
        editMessage($callbackChatId, "🔄 <b>Stripe Status Updated!</b>\n\n💳 <b>New Status:</b> ".$statusText."\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    }

    // Handle maintenance mode toggle
    if ($callbackData == 'toggle_maintenance' && $callbackUserId == '6643462826') {
        $newStatus = toggleMaintenanceMode();
        $statusText = $newStatus ? '✅ ACTIVE' : '❌ INACTIVE';
        editMessage($callbackChatId, "🔄 <b>Maintenance Mode Updated!</b>\n\n⚙️ <b>New Status:</b> ".$statusText."\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    }

    // Handle command flow callbacks
    if ($callbackData == 'start_chk') {
        setUserState($callbackUserId, 'awaiting_chk_input');
        editMessage($callbackChatId, "💵 <b>STRIPE CHARGE (USD)</b>\n\n📝 Please send card details:\n<code>[Amount] cc|mm|yy|cvv</code>\n\n<b>Example:</b> <code>1 4532123456789012|12|2025|123</code>\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    } elseif ($callbackData == 'start_inr') {
        setUserState($callbackUserId, 'awaiting_inr_input');
        editMessage($callbackChatId, "💴 <b>STRIPE CHARGE (INR)</b>\n\n📝 Please send card details:\n<code>[Amount] cc|mm|yy|cvv</code>\n\n<b>Example:</b> <code>100 4532123456789012|12|2025|123</code>\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    } elseif ($callbackData == 'start_bin') {
        setUserState($callbackUserId, 'awaiting_bin_input');
        editMessage($callbackChatId, "🔍 <b>BIN LOOKUP</b>\n\n📝 Please send BIN number:\n<code>xxxxxx</code>\n\n<b>Example:</b> <code>453212</code>\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    } elseif ($callbackData == 'start_massbin') {
        setUserState($callbackUserId, 'awaiting_massbin_input');
        editMessage($callbackChatId, "📊 <b>MASS BIN LOOKUP</b>\n\n📝 Please send multiple BINs:\n<code>bin1 bin2 bin3</code>\n\n<b>Example:</b> <code>453212 411111 543210</code>\n<i>Max: 10 BINs</i>\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    } elseif ($callbackData == 'start_sk') {
        setUserState($callbackUserId, 'awaiting_sk_input');
        editMessage($callbackChatId, "🔑 <b>STRIPE KEY CHECK</b>\n\n📝 Please send Stripe key:\n<code>sk_live_xxxxxxxxxx</code>\n\n<b>Example:</b> <code>sk_live_abc123def456</code>\n\n⚡ <b>Bot By:</b> @Calv_M", $callbackMessageId);
        exit;
    }

    // Handle info callbacks with back button
    if ($callbackData == 'info_chk') {
        $msg = "💵 <b>STRIPE CHARGE (USD)</b>\n\n";
        if (!isStripeEnabled() || isMaintenanceMode()) {
            $msg .= "╔═══════════════════════════╗\n";
            $msg .= "║   ✍️ <i>Currently Offline</i>   ║\n";
            $msg .= "╚═══════════════════════════╝\n\n";
            $msg .= "🔒 <b>Service Temporarily Unavailable</b>\n\n";
            $msg .= "⚠️ This feature is currently under maintenance.\n";
            $msg .= "Please check back later or contact the owner.\n\n";
        } else {
            $msg .= "📝 Click below to start checking:\n\n";
        }
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [];
        if (isStripeEnabled() && !isMaintenanceMode()) {
            $buttons[] = [['text' => '▶️ Start Check', 'callback_data' => 'start_chk']];
        }
        $buttons[] = [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'info_inr') {
        $msg = "💴 <b>STRIPE CHARGE (INR)</b>\n\n";
        if (!isStripeEnabled() || isMaintenanceMode()) {
            $msg .= "╔═══════════════════════════╗\n";
            $msg .= "║   ✍️ <i>Currently Offline</i>   ║\n";
            $msg .= "╚═══════════════════════════╝\n\n";
            $msg .= "🔒 <b>Service Temporarily Unavailable</b>\n\n";
            $msg .= "⚠️ This feature is currently under maintenance.\n";
            $msg .= "Please check back later or contact the owner.\n\n";
        } else {
            $msg .= "📝 Click below to start checking:\n\n";
        }
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [];
        if (isStripeEnabled() && !isMaintenanceMode()) {
            $buttons[] = [['text' => '▶️ Start Check', 'callback_data' => 'start_inr']];
        }
        $buttons[] = [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'info_bin') {
        // Set user state to awaiting BIN input
        setUserState($callbackUserId, 'awaiting_bin_input');

        $msg = "🔍 <b>BIN LOOKUP</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 <b>Please send the BIN number</b>\n";
        $msg .= "   (First 6 digits of card)\n\n";
        $msg .= "<b>Example:</b> <code>456789</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '❌ Cancel', 'callback_data' => 'back_to_menu']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'info_massbin') {
        // Set user state to awaiting mass BIN input
        setUserState($callbackUserId, 'awaiting_massbin_input');

        $msg = "📊 <b>MASS BIN LOOKUP</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 <b>Send multiple BINs</b>\n";
        $msg .= "   (One per line or space-separated)\n\n";
        $msg .= "<b>Example:</b>\n";
        $msg .= "<code>456789 456789 456789</code>\n\n";
        $msg .= "📊 <b>Max:</b> 10 BINs at once\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '❌ Cancel', 'callback_data' => 'back_to_menu']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'info_sk') {
        $msg = "🔑 <b>STRIPE KEY CHECK</b>\n\n📝 Click below to start checking:\n\n⚡ <b>Bot By:</b> @Calv_M";
        $buttons = [
            [['text' => '▶️ Start Check', 'callback_data' => 'start_sk']],
            [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
        ];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cmd_id') {
        $msg = "━━━━━━━━━━━━━━━━━━\n";
        $msg .= "👤 <b>Your Information</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🆔 <b>Telegram ID:</b> <code>".$callbackUserId."</code>\n";
        $msg .= "👥 <b>Group ID:</b> <code>".$callbackChatId."</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    } elseif ($callbackData == 'cmd_resetwebhook' && $callbackUserId == '6643462826') {
        editMessage($callbackChatId, "🔄 <b>Checking webhook status...</b>\n\nPlease wait...", $callbackMessageId);
        sleep(1);

        // Get webhook info
        $webhookInfoUrl = $GLOBALS['website']."/getWebhookInfo";
        $webhookInfo = json_decode(file_get_contents($webhookInfoUrl), true);

        // Send detailed PM to owner
        if ($webhookInfo && isset($webhookInfo['result'])) {
            $result = $webhookInfo['result'];

            $pmMsg = "╔══════════════════════════╗\n";
            $pmMsg .= "║   🔌 <b>WEBHOOK DIAGNOSTICS</b> ║\n";
            $pmMsg .= "╚══════════════════════════╝\n\n";
            $pmMsg .= "<b>═══ 🌐 CONNECTION STATUS ═══</b>\n\n";

            if (!empty($result['url'])) {
                $pmMsg .= "✅ <b>Webhook URL:</b> Active\n";
                $pmMsg .= "🔗 <b>URL:</b>\n<code>".$result['url']."</code>\n\n";
            } else {
                $pmMsg .= "❌ <b>Webhook URL:</b> Not Set\n\n";
            }

            $pmMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $pmMsg .= "<b>═══ 📊 STATISTICS ═══</b>\n\n";
            $pmMsg .= "📩 <b>Pending Updates:</b> ".($result['pending_update_count'] ?? 0)."\n";
            $pmMsg .= "🔌 <b>Max Connections:</b> ".($result['max_connections'] ?? 40)."\n";
            $pmMsg .= "🔢 <b>Allowed Updates:</b> ".count($result['allowed_updates'] ?? [])."\n\n";

            if (isset($result['last_error_date']) && $result['last_error_date'] > 0) {
                $pmMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $pmMsg .= "<b>═══ ⚠️ LAST ERROR ═══</b>\n\n";
                $pmMsg .= "🔴 <b>Error Message:</b>\n".($result['last_error_message'] ?? 'Unknown')."\n\n";
                $pmMsg .= "⏰ <b>Error Time:</b>\n".date('Y-m-d H:i:s', $result['last_error_date'])."\n\n";
            } else {
                $pmMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $pmMsg .= "✅ <b>No Recent Errors</b>\n\n";
            }

            $pmMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $pmMsg .= "<b>═══ 💡 TROUBLESHOOTING ═══</b>\n\n";
            $pmMsg .= "🔹 <b>If webhook fails:</b>\n";
            $pmMsg .= "  1. Check SSL certificate\n";
            $pmMsg .= "  2. Verify server is accessible\n";
            $pmMsg .= "  3. Check port 443/8443 open\n";
            $pmMsg .= "  4. Test webhook URL manually\n\n";
            $pmMsg .= "🔹 <b>Common Issues:</b>\n";
            $pmMsg .= "  • Server downtime\n";
            $pmMsg .= "  • SSL certificate expired\n";
            $pmMsg .= "  • Firewall blocking requests\n";
            $pmMsg .= "  • PHP execution timeout\n\n";
            $pmMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $pmMsg .= "📅 <b>Report Generated:</b> ".date('Y-m-d H:i:s')."\n\n";
            $pmMsg .= "⚡ <b>Bot By:</b> @Calv_M";

            // Send PM
            sendMessage($callbackUserId, $pmMsg, null);
        }

        $msg = "✅ <b>Webhook Diagnostics Complete!</b>\n\n";
        $msg .= "📬 <b>Report sent to your PM.</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [];
        $buttons[] = [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cmd_stats' && $callbackUserId == '6643462826') {
        $allUsers = getAllRegisteredUsers();
        if (!$allUsers) $allUsers = [];
        $totalUsers = count($allUsers);
        $totalChecks = 0;
        $totalBinChecks = 0;

        $msg = "📊 <b>USER STATISTICS</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "👥 <b>Total Users:</b> ".$totalUsers."\n\n";

        if ($totalUsers > 0) {
            $counter = 1;
            foreach ($allUsers as $uid => $userData) {
                $userChecks = $userData['total_checks'] ?? 0;
                $binChecks = $userData['bin_checks'] ?? 0;
                $totalChecks += $userChecks;
                $totalBinChecks += $binChecks;

                $msg .= "<b>".$counter.". User Details:</b>\n";
                $msg .= "🆔 <b>ID:</b> <code>".$uid."</code>\n";
                $msg .= "👤 <b>Username:</b> @".($userData['username'] ?? 'Unknown')."\n";
                $msg .= "📝 <b>Name:</b> ".($userData['firstname'] ?? 'Unknown')." ".($userData['lastname'] ?? '')."\n";
                $msg .= "📅 <b>Registered:</b> ".($userData['registered_at'] ?? 'Unknown')."\n";
                $msg .= "💳 <b>Card Checks:</b> ".$userChecks."\n";
                $msg .= "🔍 <b>BIN Checks:</b> ".$binChecks."\n\n";
                $counter++;
            }

            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📊 <b>TOTALS:</b>\n";
            $msg .= "💳 <b>Card Checks:</b> ".$totalChecks."\n";
            $msg .= "🔍 <b>BIN Checks:</b> ".$totalBinChecks."\n";
        }

        $msg .= "\n⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    } elseif ($callbackData == 'cmd_banned' && $callbackUserId == '6643462826') {
        $bannedUsers = getAllBannedUsers();
        if (!$bannedUsers) $bannedUsers = [];
        $totalBanned = count($bannedUsers);
        $allUsers = getAllRegisteredUsers();
        if (!$allUsers) $allUsers = [];

        $msg = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🚫 <b>BANNED USERS LIST</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📊 <b>Total Banned:</b> ".$totalBanned."\n\n";

        if ($totalBanned > 0) {
            $counter = 1;
            foreach ($bannedUsers as $uid => $banData) {
                $userData = $allUsers[$uid] ?? [];
                $msg .= "<b>".$counter.". Banned User:</b>\n";
                $msg .= "🆔 <b>ID:</b> <code>".$uid."</code>\n";
                $msg .= "👤 <b>Username:</b> @".($userData['username'] ?? 'Unknown')."\n";
                $msg .= "📝 <b>Name:</b> ".($userData['firstname'] ?? 'Unknown')." ".($userData['lastname'] ?? '')."\n";
                $msg .= "📅 <b>Banned At:</b> ".$banData['banned_at']."\n\n";
                $counter++;
            }
        }

        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    } elseif ($callbackData == 'cmd_refreshserver' && $callbackUserId == '6643462826') {
        editMessage($callbackChatId, "🔄 <b>Restarting PHP Server...</b>\n\n⏳ Please wait while the server restarts...", $callbackMessageId);

        // Kill all PHP server processes
        exec('pkill -9 -f "php -S" 2>&1', $killOutput);
        sleep(3);

        // Start a new PHP server in the background
        $startCmd = 'cd '.getcwd().' && nohup php -S 0.0.0.0:8080 bot.php > server.log 2>&1 & echo $!';
        exec($startCmd, $startOutput, $return_var);
        sleep(3);

        // Check if server started successfully
        $serverRunning = trim(shell_exec('pgrep -f "php -S 0.0.0.0:8080"'));

        $buttons = [];

        if (!empty($serverRunning)) {
            $msg = "✅ <b>Server Restart Successful!</b>\n";
            $msg .= "╔═══════════════════════════╗\n";
            $msg .= "║   🟢 STATUS: ONLINE       ║\n";
            $msg .= "╚═══════════════════════════╝\n\n";
            $msg .= "🔧 <b>Server Details:</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🌐 <b>Status:</b> Running\n";
            $msg .= "📍 <b>Port:</b> 8080\n";
            $msg .= "🆔 <b>Process ID:</b> ".$serverRunning."\n";
            $msg .= "⏰ <b>Restart Time:</b> ".date('H:i:s')."\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "✅ Bot is now accepting requests.\n\n";
            $msg .= "⚡ <b>Bot By:</b> @Calv_M";

            $buttons[] = [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']];
        } else {
            $msg = "🔴 <b>Bot Offline Alert!</b>\n";
            $msg .= "╔═══════════════════════════╗\n";
            $msg .= "║   ⚠️ SERVER NOT RUNNING   ║\n";
            $msg .= "╚═══════════════════════════╝\n\n";
            $msg .= "❌ <b>Server Status:</b> Offline\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "⚠️ The restart command was executed, but the server process is not running.\n\n";
            $msg .= "🔧 <b>Possible Issues:</b>\n";
            $msg .= "• Port 8080 may be in use\n";
            $msg .= "• PHP executable not found\n";
            $msg .= "• Permission issues\n";
            $msg .= "• File path incorrect\n\n";
            $msg .= "💡 <b>What to do:</b>\n";
            $msg .= "1. Check server logs\n";
            $msg .= "2. Try restarting manually\n";
            $msg .= "3. Verify bot file location\n";
            $msg .= "4. Check port availability\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "⚡ <b>Bot By:</b> @Calv_M";

            $buttons[] = [['text' => '🔄 Try Again', 'callback_data' => 'cmd_refreshserver']];
            $buttons[] = [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']];
        }

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    } elseif ($callbackData == 'prompt_delete_user' && $callbackUserId == '6643462826') {
        // Set user state for delete user flow
        setUserState($callbackUserId, 'awaiting_user_delete');

        $msg = "🗑️ <b>DELETE USER</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 <b>Send the User ID to delete</b>\n\n";
        $msg .= "<b>Example:</b> <code>123456789</code>\n\n";
        $msg .= "⚠️ <b>Warning:</b> This will permanently\n";
        $msg .= "   remove the user from the database.\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '❌ Cancel', 'callback_data' => 'back_to_menu']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;

    } elseif ($callbackData == 'prompt_ban_user' && $callbackUserId == '6643462826') {
        // Set user state for ban user flow
        setUserState($callbackUserId, 'awaiting_user_ban');

        $msg = "🚫 <b>BAN USER</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 <b>Send the User ID to ban</b>\n\n";
        $msg .= "<b>Example:</b> <code>123456789</code>\n\n";
        $msg .= "⚠️ <b>Note:</b> Banned users cannot\n";
        $msg .= "   use any bot features.\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '❌ Cancel', 'callback_data' => 'back_to_menu']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cmd_bincache' && $callbackUserId == '6643462826') {
        // BIN Cache Management
        $cacheFile = 'bin_cache.json';
        $binCache = [];

        if (file_exists($cacheFile)) {
            $binCache = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        $totalCached = count($binCache);

        $msg = "╔════════════════════════════╗\n";
        $msg .= "║  🗂️ <b>BIN CACHE MANAGER</b>    ║\n";
        $msg .= "╚════════════════════════════╝\n\n";
        $msg .= "📊 <b>Cache Statistics:</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💾 <b>Total Cached BINs:</b> ".$totalCached."\n";
        $msg .= "📁 <b>Cache File:</b> bin_cache.json\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🛠️ <b>Available Actions:</b>\n\n";
        $msg .= "• Delete specific BIN from cache\n";
        $msg .= "• Clear entire cache\n";
        $msg .= "• View cached BINs\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [];
        $buttons[] = [['text' => '🗑️ Delete Specific BIN', 'callback_data' => 'cache_delete_bin']];
        $buttons[] = [['text' => '🧹 Clear All Cache', 'callback_data' => 'cache_clear_all']];
        $buttons[] = [['text' => '📋 View Cached BINs', 'callback_data' => 'cache_view_bins']];
        $buttons[] = [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cache_delete_bin' && $callbackUserId == '6643462826') {
        setUserState($callbackUserId, 'awaiting_bin_delete');

        $msg = "🗑️ <b>DELETE BIN FROM CACHE</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 <b>Send the BIN to delete:</b>\n\n";
        $msg .= "<b>Example:</b> <code>453212</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '❌ Cancel', 'callback_data' => 'cmd_bincache']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cache_clear_all' && $callbackUserId == '6643462826') {
        file_put_contents('bin_cache.json', '{}');

        $msg = "✅ <b>Cache Cleared!</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🧹 All cached BINs have been deleted.\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Cache Manager', 'callback_data' => 'cmd_bincache']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cache_view_bins' && $callbackUserId == '6643462826') {
        $cacheFile = 'bin_cache.json';
        $binCache = [];

        if (file_exists($cacheFile)) {
            $binCache = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        $msg = "📋 <b>CACHED BINS</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        if (count($binCache) > 0) {
            $counter = 1;
            foreach ($binCache as $bin => $data) {
                if ($counter > 20) { // Limit to 20 to avoid message too long
                    $msg .= "\n... and ".(count($binCache) - 20)." more\n";
                    break;
                }
                $msg .= "<b>".$counter.".</b> <code>".$bin."</code> - ".($data['brand'] ?? 'Unknown')."\n";
                $counter++;
            }
        } else {
            $msg .= "⚠️ Cache is empty.\n";
        }

        $msg .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Cache Manager', 'callback_data' => 'cmd_bincache']]];

        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        exit;
    } elseif ($callbackData == 'cmd_botstatus' && $callbackUserId == '6643462826') {
        $allUsers = getAllRegisteredUsers();
        $bannedUsers = getAllBannedUsers();
        if (!$allUsers) $allUsers = [];
        if (!$bannedUsers) $bannedUsers = [];

        $totalUsers = count($allUsers);
        $totalBanned = count($bannedUsers);
        $totalChecks = 0;
        $totalBinChecks = 0;

        foreach ($allUsers as $userData) {
            $totalChecks += $userData['total_checks'] ?? 0;
            $totalBinChecks += $userData['bin_checks'] ?? 0;
        }

        // Get server boot time
        $uptime = trim(shell_exec('uptime -s 2>/dev/null || echo ""'));
        if (empty($uptime)) {
            $uptime = date('Y-m-d H:i:s', filectime(__FILE__));
        }

        // Check if PHP server is running
        $serverStatus = trim(shell_exec('pgrep -f "php -S 0.0.0.0:8080"'));
        $serverStatusText = !empty($serverStatus) ? "✅ Running (PID: ".$serverStatus.")" : "⚠️ Not Running";

        // Get memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $memoryUsage = round($memoryUsage, 2);


        $msg = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📡 <b>BOT STATUS REPORT</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🤖 <b>Bot:</b> ✅ Online\n";
        $msg .= "🌐 <b>Server:</b> ".$serverStatusText."\n";
        $msg .= "⏱️ <b>Started:</b> ".$uptime."\n";
        $msg .= "💾 <b>Memory:</b> ".$memoryUsage." MB\n";
        $msg .= "💳 <b>Stripe:</b> ".(isStripeEnabled() ? '🟢 Online' : '🔴 Offline')."\n";
        $msg .= "⚙️ <b>Maintenance:</b> ".(isMaintenanceMode() ? '🔴 Active' : '🟢 Inactive')."\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📊 <b>USER STATISTICS</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "👥 <b>Registered:</b> ".$totalUsers."\n";
        $msg .= "🚫 <b>Banned:</b> ".$totalBanned."\n";
        $msg .= "💳 <b>Card Checks:</b> ".$totalChecks."\n";
        $msg .= "🔍 <b>BIN Checks:</b> ".$totalBinChecks."\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📅 <b>Report:</b> ".date('Y-m-d H:i:s')."\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        $url = $GLOBALS['website']."/editMessageText";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $callbackChatId,
            'message_id' => $callbackMessageId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }

    exit;
}

//================================================================================
// BOT COMMANDS
//================================================================================

// Owner bypass - always registered
$isOwner = ($userId == '6643462826');

// Check if bot is under maintenance
if (!$isOwner && isMaintenanceMode()) {
    $msg = "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "⚙️ <b>MAINTENANCE MODE</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "⚠️ The bot is currently undergoing maintenance.\n";
    $msg .= "We apologize for the inconvenience.\n";
    $msg .= "Please try again later.\n\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    sendMessage($chatId, $msg, $message_id);
    exit;
}

// Check if user is registered for /start command
if ((strpos($message, "/start") === 0)||(strpos($message, "!start") === 0)){
    if (!$isOwner && !isUserRegistered($userId)) {
        // New user - show registration button
        $msg = "╔════════════════════════════════╗\n";
        $msg .= "║  👋 <b>WELCOME TO CHECKER BOT</b>  ║\n";
        $msg .= "╚════════════════════════════════╝\n\n";
        $msg .= "🎯 <b>Features:</b>\n";
        $msg .= "  • Stripe Card Checking (USD/INR)\n";
        $msg .= "  • BIN Lookup System\n";
        $msg .= "  • Mass BIN Checker\n";
        $msg .= "  • Stripe Key Validation\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 <b>To get started, please register:</b>\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '✅ Register Now', 'callback_data' => 'register_user']]];

        sendMessageWithButtons($chatId, $msg, $buttons, $message_id);
        exit;
    }

    // Registered user or owner - show full menu (without text, only buttons)
    $buttons = [];

    // Horizontal 2-column layout
    $buttons[] = [
        ['text' => '💵 STRIPE CHARGE (USD)', 'callback_data' => 'info_chk'],
        ['text' => '💴 STRIPE CHARGE (INR)', 'callback_data' => 'info_inr']
    ];
    $buttons[] = [
        ['text' => '🔍 BIN LOOKUP', 'callback_data' => 'info_bin'],
        ['text' => '📊 MASS BIN LOOKUP', 'callback_data' => 'info_massbin']
    ];
    $buttons[] = [['text' => '🔑 STRIPE KEY CHECK', 'callback_data' => 'info_sk']];
    $buttons[] = [['text' => '🆔 Get IDs', 'callback_data' => 'cmd_id']];

    if ($isOwner) {
        $buttons[] = [
            ['text' => '🔄 Restart Server', 'callback_data' => 'cmd_refreshserver'],
            ['text' => '🔌 Fix Webhook', 'callback_data' => 'cmd_resetwebhook']
        ];
        $buttons[] = [
            ['text' => '🗑️ Delete User', 'callback_data' => 'prompt_delete_user'],
            ['text' => '🚫 Ban User', 'callback_data' => 'prompt_ban_user']
        ];
        $buttons[] = [
            ['text' => '📊 Stats', 'callback_data' => 'cmd_stats'],
            ['text' => '📋 Banned List', 'callback_data' => 'cmd_banned']
        ];
        $buttons[] = [
            ['text' => '📡 Bot Status', 'callback_data' => 'cmd_botstatus'],
            ['text' => '🗂️ Manage BIN Cache', 'callback_data' => 'cmd_bincache']
        ];
        $buttons[] = [['text' => isStripeEnabled() ? '🔴 Stripe OFF' : '🟢 Stripe ON', 'callback_data' => 'toggle_stripe']];
        $buttons[] = [['text' => isMaintenanceMode() ? '🔴 Maintenance OFF' : '🛠️ Maintenance ON', 'callback_data' => 'toggle_maintenance']];
    }

    sendMessageWithButtons($chatId, "🤖 <b>Bot Menu:</b>", $buttons, $message_id);
    exit;
}

// Registration check for all other commands (except /start)
if (!$isOwner && !isUserRegistered($userId)) {
    $msg = "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "❌ <b>Access Denied</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "⚠️ You need to register first to use this bot.\n\n";
    $msg .= "📝 <b>Type /start to register</b>\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    sendMessage($chatId, $msg, $message_id);
    exit;
}

// Ban check for all commands (except owner)
if (!$isOwner && isUserBanned($userId)) {
    $msg = "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🚫 <b>BANNED</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "❌ You have been banned from using this bot.\n\n";
    $msg .= "📧 Contact the bot owner for more information.\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    sendMessage($chatId, $msg, $message_id);
    exit;
}

// Check if user is in a command flow state
$userState = getUserState($userId);
if ($userState && !empty($message)) {
    $state = $userState['state'];

    if ($state == 'awaiting_chk_input') {
        clearUserState($userId);
        $message = '/chk ' . $message;
    } elseif ($state == 'awaiting_inr_input') {
        clearUserState($userId);
        $message = '/inr ' . $message;
    } elseif ($state == 'awaiting_bin_input') {
        clearUserState($userId);
        $message = '/bin ' . $message;
    } elseif ($state == 'awaiting_massbin_input') {
        clearUserState($userId);
        $message = '/massbin ' . $message;
    } elseif ($state == 'awaiting_sk_input') {
        clearUserState($userId);
        $message = '/sk ' . $message;
    } elseif ($state == 'awaiting_user_delete') {
        $targetUserId = trim($message);

        if (!is_numeric($targetUserId)) {
            sendMessage($chatId, "❌ <b>Invalid User ID</b>\n\nPlease send numbers only.", $message_id);
            exit;
        }

        if (!isUserRegistered($targetUserId)) {
            sendMessage($chatId, "⚠️ <b>User Not Found</b>\n\n<b>User ID:</b> <code>".$targetUserId."</code> is not registered.", $message_id);
            clearUserState($userId);
            exit;
        }

        deleteUser($targetUserId);
        clearUserState($userId);

        $msg = "✅ <b>User Deleted Successfully!</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🗑️ <b>Deleted:</b> <code>".$targetUserId."</code>\n";
        $msg .= "📅 <b>Time:</b> ".date('H:i:s')."\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        sendMessageWithButtons($chatId, $msg, $buttons, $message_id);
        exit;

    } elseif ($state == 'awaiting_user_ban') {
        $targetUserId = trim($message);

        if (!is_numeric($targetUserId)) {
            sendMessage($chatId, "❌ <b>Invalid User ID</b>\n\nPlease send numbers only.", $message_id);
            exit;
        }

        if (isUserBanned($targetUserId)) {
            sendMessage($chatId, "⚠️ <b>Already Banned</b>\n\n<b>User ID:</b> <code>".$targetUserId."</code> is already banned.", $message_id);
            clearUserState($userId);
            exit;
        }

        banUser($targetUserId);
        clearUserState($userId);

        $msg = "🚫 <b>User Banned Successfully!</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🚫 <b>Banned:</b> <code>".$targetUserId."</code>\n";
        $msg .= "📅 <b>Time:</b> ".date('H:i:s')."\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";

        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        sendMessageWithButtons($chatId, $msg, $buttons, $message_id);
        exit;

    } elseif ($state == 'awaiting_bin_delete' && $userId == '6643462826') {
        $binToDelete = trim($message);

        if (strlen($binToDelete) < 6) {
            sendMessage($chatId, "❌ <b>Invalid BIN</b>\n\nPlease send a valid 6-digit BIN.", $message_id);
            exit;
        }

        $binToDelete = substr($binToDelete, 0, 6);
        $cacheFile = 'bin_cache.json';
        $binCache = [];

        if (file_exists($cacheFile)) {
            $binCache = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        if (isset($binCache[$binToDelete])) {
            unset($binCache[$binToDelete]);
            file_put_contents($cacheFile, json_encode($binCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $msg = "✅ <b>BIN Deleted Successfully!</b>\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "🗑️ <b>Deleted BIN:</b> <code>".$binToDelete."</code>\n";
            $msg .= "📅 <b>Time:</b> ".date('Y-m-d H:i:s')."\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "⚡ <b>Bot By:</b> @Calv_M";
        } else {
            $msg = "⚠️ <b>BIN Not Found</b>\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "🔍 <b>BIN:</b> <code>".$binToDelete."</code>\n";
            $msg .= "This BIN is not in the cache.\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "⚡ <b>Bot By:</b> @Calv_M";
        }

        clearUserState($userId);
        $buttons = [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]];
        sendMessageWithButtons($chatId, $msg, $buttons, $message_id);
        exit;
    }
}

if ((strpos($message, "/info") === 0)||(strpos($message, "!id") === 0)||(strpos($message, "!info") === 0)||(strpos($message, "/id") === 0)||(strpos($message, "/me") === 0)){
    $buttons = [];

    // Horizontal 2-column layout
    $buttons[] = [
        ['text' => '💵 STRIPE CHARGE (USD)', 'callback_data' => 'info_chk'],
        ['text' => '💴 STRIPE CHARGE (INR)', 'callback_data' => 'info_inr']
    ];
    $buttons[] = [
        ['text' => '🔍 BIN LOOKUP', 'callback_data' => 'info_bin'],
        ['text' => '📊 MASS BIN LOOKUP', 'callback_data' => 'info_massbin']
    ];
    $buttons[] = [['text' => '🔑 STRIPE KEY CHECK', 'callback_data' => 'info_sk']];
    $buttons[] = [['text' => '🆔 Get IDs', 'callback_data' => 'cmd_id']];

    if ($userId == '6643462826') {
        $buttons[] = [
            ['text' => '🔄 Restart Server', 'callback_data' => 'cmd_refreshserver'],
            ['text' => '🔌 Fix Webhook', 'callback_data' => 'cmd_resetwebhook']
        ];
        $buttons[] = [
            ['text' => '🗑️ Delete User', 'callback_data' => 'prompt_delete_user'],
            ['text' => '🚫 Ban User', 'callback_data' => 'prompt_ban_user']
        ];
        $buttons[] = [
            ['text' => '📊 Stats', 'callback_data' => 'cmd_stats'],
            ['text' => '📋 Banned List', 'callback_data' => 'cmd_banned']
        ];
        $buttons[] = [
            ['text' => '📡 Bot Status', 'callback_data' => 'cmd_botstatus'],
            ['text' => '🗂️ Manage BIN Cache', 'callback_data' => 'cmd_bincache']
        ];
        $buttons[] = [['text' => isStripeEnabled() ? '🔴 Stripe OFF' : '🟢 Stripe ON', 'callback_data' => 'toggle_stripe']];
        $buttons[] = [['text' => isMaintenanceMode() ? '🔴 Maintenance OFF' : '🛠️ Maintenance ON', 'callback_data' => 'toggle_maintenance']];
    }

    sendMessageWithButtons($chatId, "🤖 <b>Bot Menu:</b>", $buttons, $message_id);
}
elseif ((strpos($message, "/chk") === 0)||(strpos($message, "!chk") === 0)||(strpos($message, ".chk") === 0)){
// Check if stripe is enabled or maintenance mode is on
if (!isStripeEnabled() || isMaintenanceMode()) {
    $msg = "╔═══════════════════════════╗\n";
    $msg .= "║   ✍️ <i>Currently Offline</i>   ║\n";
    $msg .= "╚═══════════════════════════╝\n\n";
    $msg .= "🔒 <b>Service Temporarily Unavailable</b>\n\n";
    $msg .= "⚠️ This feature is currently under maintenance.\n";
    $msg .= "Please check back later or contact the owner.\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    sendMessage($chatId, $msg, $message_id);
    exit;
}

// Increment user check count
incrementUserChecks($userId);

$message = substr($message, 4);

// Check if message is empty
if (empty(trim($message))) {
    sendMessage($chatId, "❌ <b>Invalid Format</b>\n\n<b>Usage:</b> <code>/chk cc|mm|yy|cvv</code>\n\n<b>Example:</b> <code>/chk 4532123456789012|12|25|123</code>", $message_id);
    exit;
}

$amt = multiexplode(array("/", ":", " ", "|"), $message)[0];
$cc = multiexplode(array(":", "/", " ", "|"), $message)[1];
$mes = multiexplode(array(":", "/", " ", "|"), $message)[2];
$ano = multiexplode(array(":", "/", " ", "|"), $message)[3];
$cvv = multiexplode(array(":", "/", " ", "|"), $message)[4];
if (empty($amt)) {
$amt = '1';
}
        $amount = $amt * 100;
        $lista = ''.$cc.'|'.$mes.'|'.$ano.'|'.$cvv.'';

        $binFirst6 = substr($cc, 0, 6);
        $fim = lookupBin($binFirst6);

        if ($fim !== false) {
            $binData = json_decode($fim, true);

            if (!$binData || (!isset($binData['scheme']) && !isset($binData['type']) && !isset($binData['brand']))) {
                sendMessage($chatId, '<b>❌ BIN Lookup Failed</b>%0A<b>Error:</b> Invalid data received. Please try again.%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
                return;
            }

            if (isset($binData['bank']['name']) && !empty($binData['bank']['name']) && $binData['bank']['name'] !== 'Unknown') {
                $bank = $binData['bank']['name'];
            } elseif (isset($data['bank']) && is_string($data['bank']) && !empty($data['bank']) && $data['bank'] !== 'Unknown') {
                $bank = $data['bank'];
            } else {
                $bank = 'Unknown';
            }

            if (isset($binData['country']['name'])) {
                $name = $binData['country']['name'];
            } elseif (isset($data['country']) && is_string($data['country'])) {
                $name = $data['country'];
            } else {
                $name = 'Unknown';
            }

            $emoji = '';
            if (isset($binData['country']['emoji'])) {
                $emoji = $binData['country']['emoji'];
                if (strpos($emoji, '\\u') !== false) {
                    $emoji = json_decode('"' . $emoji . '"');
                }
            }

            $brand = $binData['brand'] ?? $binData['BRAND'] ?? $binData['scheme'] ?? $binData['SCHEME'] ?? 'Unknown';
            if ($brand !== 'Unknown' && strtoupper($brand) === $brand) {
                $brand = ucfirst(strtolower($brand));
            }

            $scheme = $binData['scheme'] ?? $binData['SCHEME'] ?? $binData['brand'] ?? $binData['BRAND'] ?? 'Unknown';
            if ($scheme !== 'Unknown' && strtoupper($scheme) === $scheme) {
                $scheme = ucfirst(strtolower($scheme));
            }

            $type = strtolower($binData['type'] ?? $binData['TYPE'] ?? 'unknown');

            if($type === 'credit'){
            $bin = 'Credit';
            }else{
            $bin = 'Debit';
            };
        } else {
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» BIN Lookup Failed</b>%0A<b>Response -» Failed to retrieve BIN information.</b>%0A<b>Gateway -» Stripe Charge $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>", $message_id);
        }

        if(isset($bin) && $bin === 'Credit' || $bin === 'Debit'){
            // Send processing message and capture its message_id
            $processingMsg = "⏳ <b>Processing...</b>\n\n";
            $processingMsg .= "💳 <b>Checking card...</b>\n";
            $processingMsg .= "🔄 <b>Please wait...</b>";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $website."/sendMessage");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'chat_id' => $chatId,
                'text' => $processingMsg,
                'reply_to_message_id' => $message_id,
                'parse_mode' => 'HTML'
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $sentResponse = curl_exec($ch);
            curl_close($ch);

            $sentData = json_decode($sentResponse, true);
            $processingMessageId = $sentData['result']['message_id'] ?? null;

            // Track processing time
            $startTime = microtime(true);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERPWD, $sk. ':' . '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=card&card[number]='.$cc.'&card[exp_month]='.$mes.'&card[exp_year]='.$ano.'&card[cvc]='.$cvv.'&billing_details[address][line1]=36&billing_details[address][line2]=Regent Street&billing_details[address][city]=Jamestown&billing_details[address][postal_code]=14701&billing_details[address][state]=New York&billing_details[address][country]=US&billing_details[email]='.$mail.'@gmail.com&billing_details[name]=@Calv_M Mittal');
            $result1 = curl_exec($ch);
            curl_close($ch);

            $tok1 = GetStr($result1,'"id": "','"');
            $msg1 = GetStr($result1,'"message": "','"');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERPWD, $sk. ':' . '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'amount='.$amount.'&currency=usd&payment_method_types[]=card&description=@Calv_M Donation&payment_method='.$tok1.'&confirm=true&off_session=true');
            $result2 = curl_exec($ch);
            curl_close($ch);
            $msg2 = GetStr($result2,'"message": "','"');
            $rcp = trim(strip_tags(GetStr($result2,'"receipt_url": "','"')));

            // Calculate processing time
            $processingTime = round((microtime(true) - $startTime), 2);

            if(strpos($result2, '"seller_message": "Payment complete."' )) {
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>\n<b>Status -» CVV Matched ✅</b>\n<b>Response -» Successfully Charged $$amt ✅</b>\n<b>Gateway -» Stripe$ Premium</b>\n\n<b>⋆ Checked By:</b> @$usernam\n\n<b>⋆ Bot By: @Calv_M</b>\n<b>Time:</b> $processingTime seconds", $message_id);

            }
            elseif(strpos($result2, "insufficient_funds" )) {
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>\n<b>Status -» CVV Matched ✅</b>\n<b>Response -» Insufficient Funds</b>\n<b>Gateway -» Stripe$ Premium</b>\n\n<b>⋆ Checked By:</b> @$usernam\n\n<b>⋆ Bot By: @Calv_M</b>\n<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "card_error_authentication_required")) || (strpos($result2, "card_error_authentication_required"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
             sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» 3D Card</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif(strpos($result2, '"cvc_check": "pass"')){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» Payment Cannot Be Completed</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif(strpos($result2, '"code": "incorrect_cvc"')){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CCN Matched ✅</b>%0A<b>Response -» CVV MISSMATCH</b>%0A<b>Gateway -» Stripe Premium $$amt</b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif(strpos($result1, '"code": "incorrect_cvc"')){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CCN Matched ✅</b>%0A<b>Response -» CVV MISSMATCH</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "transaction_not_allowed")) || (strpos($result2, "transaction_not_allowed"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» Transaction Not Allowed</b>%0A<b>Gateway -» Stripe Premium $$amt</b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "fraudulent")) || (strpos($result2, "fraudulent"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Fraudulent</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "expired_card")) || (strpos($result2, "expired_card"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Expired Card</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "generic_declined")) || (strpos($result2, "generic_declined"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Generic Declined</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);
            }
            elseif ((strpos($result1, "do_not_honor")) || (strpos($result2, "do_not_honor"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Do Not Honor</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, 'rate_limit')) || (strpos($result2, 'rate_limit'))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» SK IS AT RATE LIMIT</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}

            elseif ((strpos($result1, "Your card was declined.")) || (strpos($result2, "Your card was declined."))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Generic Declined</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, ' "message": "Your card number is incorrect."')) || (strpos($result2, ' "message": "Your card number is incorrect."'))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Card Number Is Incorrect</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium $$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            else {
            // Format to match screenshot
            $msg = "<b>Error ⚠️</b>\n\n";
            $msg .= "<b>Card:</b> <code>$lista</code>\n";
            $msg .= "<b>Gateway:</b> Stripe Premium\n";
            $msg .= "<b>Response:</b> Error: Unknown Response\n\n";
            $msg .= "<b>Info:</b> ".strtoupper($scheme)." - ".strtoupper($bin)." - ".strtoupper($brand)."\n";
            $msg .= "<b>Issuer:</b> ".strtoupper($bank)."\n";
            $msg .= "<b>Country:</b> ".strtoupper($name)." ".$emoji."\n\n";
            $msg .= "<b>Time:</b> $processingTime seconds";

            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, $msg, $message_id);
            };
        }

        }

        elseif ((strpos($message, "/inr") === 0)||(strpos($message, "!inr") === 0)||(strpos($message, ".inr") === 0)){
// Check if stripe is enabled or maintenance mode is on
if (!isStripeEnabled() || isMaintenanceMode()) {
    $msg = "╔═══════════════════════════╗\n";
    $msg .= "║   ✍️ <i>Currently Offline</i>   ║\n";
    $msg .= "╚═══════════════════════════╝\n\n";
    $msg .= "🔒 <b>Service Temporarily Unavailable</b>\n\n";
    $msg .= "⚠️ This feature is currently under maintenance.\n";
    $msg .= "Please check back later or contact the owner.\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    sendMessage($chatId, $msg, $message_id);
    exit;
}

// Increment user check count
incrementUserChecks($userId);

$message = substr($message, 4);

// Check if message is empty
if (empty(trim($message))) {
    sendMessage($chatId, "❌ <b>Invalid Format</b>\n\n<b>Usage:</b> <code>/inr [amount] cc|mm|yy|cvv</code>\n\n<b>Example:</b> <code>/inr 100 4532123456789012|12|2025|123</code>", $message_id);
    exit;
}

$amt = multiexplode(array("/", ":", " ", "|"), $message)[0];
$cc = multiexplode(array(":", "/", " ", "|"), $message)[1];
$mes = multiexplode(array(":", "/", " ", "|"), $message)[2];
$ano = multiexplode(array(":", "/", " ", "|"), $message)[3];
$cvv = multiexplode(array(":", "/", " ", "|"), $message)[4];
if (empty($amt)) {
$amt = '100';
}
        $amount = $amt * 100;
        $lista = ''.$cc.'|'.$mes.'|'.$ano.'|'.$cvv.'';

        $binFirst6 = substr($cc, 0, 6);
        $fim = lookupBin($binFirst6);

        if ($fim !== false) {
            $binData = json_decode($fim, true);

            if (!$binData || (!isset($binData['scheme']) && !isset($binData['type']) && !isset($binData['brand']))) {
                sendMessage($chatId, '<b>❌ BIN Lookup Failed</b>%0A<b>Error:</b> Invalid data received. Please try again.%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
                return;
            }

            if (isset($binData['bank']['name']) && !empty($binData['bank']['name']) && $binData['bank']['name'] !== 'Unknown') {
                $bank = $binData['bank']['name'];
            } elseif (isset($data['bank']) && is_string($data['bank']) && !empty($data['bank']) && $data['bank'] !== 'Unknown') {
                $bank = $data['bank'];
            } else {
                $bank = 'Unknown';
            }

            if (isset($binData['country']['name'])) {
                $name = $binData['country']['name'];
            } elseif (isset($data['country']) && is_string($data['country'])) {
                $name = $data['country'];
            } else {
                $name = 'Unknown';
            }

            $emoji = '';
            if (isset($binData['country']['emoji'])) {
                $emoji = $binData['country']['emoji'];
                if (strpos($emoji, '\\u') !== false) {
                    $emoji = json_decode('"' . $emoji . '"');
                }
            }

            $brand = $binData['brand'] ?? $binData['BRAND'] ?? $binData['scheme'] ?? $binData['SCHEME'] ?? 'Unknown';
            if ($brand !== 'Unknown' && strtoupper($brand) === $brand) {
                $brand = ucfirst(strtolower($brand));
            }

            $scheme = $binData['scheme'] ?? $binData['SCHEME'] ?? $binData['brand'] ?? $binData['BRAND'] ?? 'Unknown';
            if ($scheme !== 'Unknown' && strtoupper($scheme) === $scheme) {
                $scheme = ucfirst(strtolower($scheme));
            }

            $type = strtolower($binData['type'] ?? $binData['TYPE'] ?? 'unknown');

            if($type === 'credit'){
            $bin = 'Credit';
            }else{
            $bin = 'Debit';
            };
        } else {
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» BIN Lookup Failed</b>%0A<b>Response -» Failed to retrieve BIN information.</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>", $message_id);
        }

        if(isset($bin) && $bin === 'Credit' || $bin === 'Debit'){
            // Send processing message and capture its message_id
            $processingMsg = "⏳ <b>Processing...</b>\n\n";
            $processingMsg .= "💳 <b>Checking card...</b>\n";
            $processingMsg .= "🔄 <b>Please wait...</b>";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $website."/sendMessage");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'chat_id' => $chatId,
                'text' => $processingMsg,
                'reply_to_message_id' => $message_id,
                'parse_mode' => 'HTML'
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $sentResponse = curl_exec($ch);
            curl_close($ch);

            $sentData = json_decode($sentResponse, true);
            $processingMessageId = $sentData['result']['message_id'] ?? null;

            // Track processing time
            $startTime = microtime(true);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERPWD, $sk. ':' . '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=card&card[number]='.$cc.'&card[exp_month]='.$mes.'&card[exp_year]='.$ano.'&card[cvc]='.$cvv.'&billing_details[address][line1]=36&billing_details[address][line2]=Regent Street&billing_details[address][city]=Jamestown&billing_details[address][postal_code]=14701&billing_details[address][state]=New York&billing_details[address][country]=US&billing_details[email]='.$mail.'@gmail.com&billing_details[name]=@Calv_M Mittal');
            $result1 = curl_exec($ch);
            curl_close($ch);

            $tok1 = GetStr($result1,'"id": "','"');
            $msg1 = GetStr($result1,'"message": "','"');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERPWD, $sk. ':' . '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'amount='.$amount.'&currency=inr&payment_method_types[]=card&description=@Calv_M Donation&payment_method='.$tok1.'&confirm=true&off_session=true');
            $result2 = curl_exec($ch);
            curl_close($ch);
            $msg2 = GetStr($result2,'"message": "','"');
            $rcp = trim(strip_tags(GetStr($result2,'"receipt_url": "','"')));

            // Calculate processing time
            $processingTime = round((microtime(true) - $startTime), 2);

            if(strpos($result2, '"seller_message": "Payment complete."' )) {
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» Successfully Charged ₹$amt ✅</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);
            }
            elseif(strpos($result2, "insufficient_funds" )) {
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» Insufficient Funds</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "card_error_authentication_required")) || (strpos($result2, "card_error_authentication_required"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
             sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» 3D Card</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif(strpos($result2,'"cvc_check": "pass"')){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» Payment Cannot Be Completed</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif(strpos($result2,'"code": "incorrect_cvc"')){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CCN Matched ✅</b>%0A<b>Response -» CVV MISSMATCH</b>%0A<b>Gateway -» Stripe Premium ₹$amt</b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif(strpos($result1,'"code": "incorrect_cvc"')){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CCN Matched ✅</b>%0A<b>Response -» CVV MISSMATCH</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "transaction_not_allowed")) || (strpos($result2, "transaction_not_allowed"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» CVV Matched ✅</b>%0A<b>Response -» Transaction Not Allowed</b>%0A<b>Gateway -» Stripe Premium ₹$amt</b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "fraudulent")) || (strpos($result2, "fraudulent"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Fraudulent</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "expired_card")) || (strpos($result2, "expired_card"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Expired Card</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, "generic_declined")) || (strpos($result2, "generic_declined"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Generic Declined</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);
            }
            elseif ((strpos($result1, "do_not_honor")) || (strpos($result2, "do_not_honor"))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Do Not Honor</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, 'rate_limit')) || (strpos($result2, 'rate_limit'))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» SK IS AT RATE LIMIT</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}

            elseif ((strpos($result1, "Your card was declined.")) || (strpos($result2, "Your card was declined."))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Generic Declined</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            elseif ((strpos($result1, ' "message": "Your card number is incorrect."')) || (strpos($result2, ' "message": "Your card number is incorrect."'))){
            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, "<b>Card: <code>$lista</code></b>%0A<b>Status -» Card Number Is Incorrect</b>%0A<b>Response -» Declined ❌</b>%0A<b>Gateway -» Stripe Premium ₹$amt </b>%0A%0A<b>⋆ Checked By:</b> @$usernam%0A%0A<b>⋆ Bot By: @Calv_M</b>%0A<b>Time:</b> $processingTime seconds", $message_id);}
            else {
            // Format to match screenshot
            $msg = "<b>Error ⚠️</b>\n\n";
            $msg .= "<b>Card:</b> <code>$lista</code>\n";
            $msg .= "<b>Gateway:</b> Stripe Premium\n";
            $msg .= "<b>Response:</b> Error: Unknown Response\n\n";
            $msg .= "<b>Info:</b> ".strtoupper($scheme)." - ".strtoupper($bin)." - ".strtoupper($brand)."\n";
            $msg .= "<b>Issuer:</b> ".strtoupper($bank)."\n";
            $msg .= "<b>Country:</b> ".strtoupper($name)." ".$emoji."\n\n";
            $msg .= "<b>Time:</b> $processingTime seconds";

            // Delete processing message
            if ($processingMessageId) delMessage($chatId, $processingMessageId);
            sendMessage($chatId, $msg, $message_id);
            };
        }

        }

        elseif ((strpos($message, "/cmds") === 0)||(strpos($message, "!cmds") === 0)||(strpos($message, "!command") === 0)||(strpos($message, "!commands") === 0)||(strpos($message, "/commands") === 0)||(strpos($message, "/command") === 0)||(strpos($message, "/cmd") === 0)){
    $msg = "╔══════════════════════════╗\n";
    $msg .= "║   🎯 <b>COMMAND CENTER</b>    ║\n";
    $msg .= "╚══════════════════════════╝\n\n";
    $msg .= "<b>═══ 💳 CHECKER GATES ═══</b>\n\n";
    $msg .= "🔹 <b>Available Commands:</b>\n";
    $msg .= "• /chk - Stripe Premium (USD)\n";
    $msg .= "• /inr - Stripe Premium (INR)\n";
    $msg .= "• /bin - BIN Lookup\n";
    $msg .= "• /massbin - Mass BIN Lookup\n\n";
    $msg .= "<b>═══ 🛠 TOOLS ═══</b>\n\n";
    $msg .= "• /sk - Check Stripe Key\n";
    $msg .= "• /info - Bot Information\n";
    $msg .= "• /id - Your Profile\n\n";

    if ($userId == '6643462826') {
        $msg .= "<b>═══ ⚙️ ADMIN PANEL ═══</b>\n\n";
        $msg .= "• 🔄 Restart Server\n";
        $msg .= "• 🔌 Fix Webhook\n";
        $msg .= "• 🗑️ Delete User\n";
        $msg .= "• 🚫 Ban User\n";
        $msg .= "• 📊 Stats\n";
        $msg .= "• 📋 Banned List\n";
        $msg .= "• 📡 Bot Status\n";
        $msg .= "• 🗂️ Manage BIN Cache\n";
        $msg .= "• 🔴 Stripe ON/OFF\n";
        $msg .= "• 🛠️ Maintenance ON/OFF\n\n";
    }

    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";

    $buttons = [];

    // Horizontal 2-column layout
    $buttons[] = [
        ['text' => '💵 STRIPE PREMIUM (USD)', 'callback_data' => 'info_chk'],
        ['text' => '💴 STRIPE PREMIUM (INR)', 'callback_data' => 'info_inr']
    ];
    $buttons[] = [
        ['text' => '🔍 BIN LOOKUP', 'callback_data' => 'info_bin'],
        ['text' => '📊 MASS BIN LOOKUP', 'callback_data' => 'info_massbin']
    ];
    $buttons[] = [['text' => '🔑 STRIPE KEY CHECK', 'callback_data' => 'info_sk']];
    $buttons[] = [['text' => '🆔 Get IDs', 'callback_data' => 'cmd_id']];

    // Owner commands
    if ($userId == '6643462826') {
        $buttons[] = [
            ['text' => '🔄 Restart Server', 'callback_data' => 'cmd_refreshserver'],
            ['text' => '🔌 Fix Webhook', 'callback_data' => 'cmd_resetwebhook']
        ];
        $buttons[] = [
            ['text' => '🗑️ Delete User', 'callback_data' => 'prompt_delete_user'],
            ['text' => '🚫 Ban User', 'callback_data' => 'prompt_ban_user']
        ];
        $buttons[] = [
            ['text' => '📊 Stats', 'callback_data' => 'cmd_stats'],
            ['text' => '📋 Banned List', 'callback_data' => 'cmd_banned']
        ];
        $buttons[] = [
            ['text' => '📡 Bot Status', 'callback_data' => 'cmd_botstatus'],
            ['text' => '🗂️ Manage BIN Cache', 'callback_data' => 'cmd_bincache']
        ];
        $buttons[] = [['text' => isStripeEnabled() ? '🔴 Stripe OFF' : '🟢 Stripe ON', 'callback_data' => 'toggle_stripe']];
        $buttons[] = [['text' => isMaintenanceMode() ? '🔴 Maintenance OFF' : '🛠️ Maintenance ON', 'callback_data' => 'toggle_maintenance']];
    }

    sendMessageWithButtons($chatId, $msg, $buttons, $message_id);
}

//////////=========[IMPROVED MASS BIN LOOKUP WITH BATCH PROCESSING]=========//////////

elseif ((strpos($message, "/massbin") === 0)||(strpos($message, "!massbin") === 0)||(strpos($message, ".massbin") === 0)){
    $binInput = substr($message, 9);
    $binInput = trim($binInput);

    if (!empty($binInput)) {
        // Split by space, comma, or pipe
        $bins = preg_split('/[\s,|]+/', $binInput);

        // Increment BIN check count for each BIN checked
        if (count($bins) > 0) {
            for ($i = 0; $i < count($bins); $i++) {
                incrementBinChecks($userId);
            }
        }
        $bins = array_filter($bins); // Remove empty elements
        $bins = array_slice($bins, 0, 10); // Limit to 10 BINs

        if (count($bins) === 0) {
            $msg = "❌ <b>Invalid Format</b>\n";
            $msg .= "★ <b>Usage:</b> /massbin 123456 234567 345678\n";
            $msg .= "★ <b>Max:</b> 10 BINs at once\n";
            $msg .= "\n⚡ <b>Bot By:</b> @Calv_M";
            sendMessage($chatId, $msg, $message_id);
            exit;
        }

        // Send processing message and capture its message_id
        $processingMsg = "⏳ <b>Processing Mass BIN Lookup...</b>\n\n";
        $processingMsg .= "🔍 <b>Checking ".count($bins)." BINs...</b>\n";
        $processingMsg .= "🔄 <b>Please wait...</b>";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $website."/sendMessage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $chatId,
            'text' => $processingMsg,
            'reply_to_message_id' => $message_id,
            'parse_mode' => 'HTML'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $sentResponse = curl_exec($ch);
        curl_close($ch);

        $sentData = json_decode($sentResponse, true);
        $processingMessageId = $sentData['result']['message_id'] ?? null;

        // Process all BINs and build one complete message
        $validCount = 0;
        $resultMessage = "";

        foreach ($bins as $index => $binInput) {
            $bin = substr(trim($binInput), 0, 6);

            if (strlen($bin) < 6) {
                $resultMessage .= "<b>BIN:</b> <code>".$bin."</code>\n";
                $resultMessage .= "<b>Request:</b> BIN Lookup\n";
                $resultMessage .= "<b>Response:</b> Invalid ❌\n\n";
                continue;
            }

            $fim = lookupBin($bin);

            if ($fim !== false) {
                $binData = json_decode($fim, true);

                if ($binData && (isset($binData['scheme']) || isset($binData['type']) || isset($binData['brand']))) {
                    $validCount++;

                    // Extract bank name
                    if (isset($binData['bank']['name']) && !empty($binData['bank']['name']) && $binData['bank']['name'] !== 'Unknown') {
                        $bank = $binData['bank']['name'];
                    } elseif (isset($binData['bank']) && is_string($binData['bank']) && !empty($binData['bank']) && $binData['bank'] !== 'Unknown') {
                        $bank = $binData['bank'];
                    } else {
                        $bank = 'Unknown';
                    }

                    // Extract country name
                    if (isset($binData['country']['name'])) {
                        $name = $binData['country']['name'];
                    } elseif (isset($binData['country']) && is_string($binData['country'])) {
                        $name = $binData['country'];
                    } else {
                        $name = 'Unknown';
                    }

                    // Extract emoji
                    $emoji = '';
                    if (isset($binData['country']['emoji'])) {
                        $emoji = $binData['country']['emoji'];
                        // Fix double-encoded emojis like \\ud83c\\uddf0\\ud83c\\uddea
                        if (strpos($emoji, '\\u') !== false) {
                            $emoji = json_decode('"' . $emoji . '"');
                        }
                    }

                    // Extract brand
                    $brand = $binData['brand'] ?? $binData['BRAND'] ?? $binData['scheme'] ?? $binData['SCHEME'] ?? 'Unknown';
                    if ($brand !== 'Unknown' && strtoupper($brand) === $brand) {
                        // If all uppercase, convert to title case
                        $brand = ucfirst(strtolower($brand));
                    }

                    // Extract scheme
                    $scheme = $binData['scheme'] ?? $binData['SCHEME'] ?? $binData['brand'] ?? $binData['BRAND'] ?? 'Unknown';
                    if ($scheme !== 'Unknown' && strtoupper($scheme) === $scheme) {
                        // If all uppercase, convert to title case
                        $scheme = ucfirst(strtolower($scheme));
                    }

                    // Extract type
                    $type = strtolower($binData['type'] ?? $binData['TYPE'] ?? 'unknown');
                    if ($type === 'credit') {
                        $bintype = 'Credit';
                    } elseif ($type === 'debit') {
                        $bintype = 'Debit';
                    } elseif ($type === 'prepaid') {
                        $bintype = 'Prepaid';
                    } else {
                        $bintype = 'Unknown';
                    }

                    $resultMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                    $resultMessage .= "<b>BIN:</b> <code>" . $bin . "xxxxxx</code>\n";
                    $resultMessage .= "<b>Request:</b> BIN Lookup\n";
                    $resultMessage .= "<b>Response:</b> Valid ✅\n\n";
                    $resultMessage .= "<b>Info:</b> ".strtoupper($scheme)." - ".strtoupper($bintype)." - ".strtoupper($brand)."\n";
                    $resultMessage .= "<b>Issuer:</b> ".strtoupper($bank)."\n";
                    $resultMessage .= "<b>Country:</b> ".strtoupper($name)." ".$emoji."\n\n";
                } else {
                    $resultMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                    $resultMessage .= "<b>BIN:</b> <code>".$bin."xxxxxx</code>\n";
                    $resultMessage .= "<b>Request:</b> BIN Lookup\n";
                    $resultMessage .= "<b>Response:</b> Declined ❌\n\n";
                }
            } else {
                $resultMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $resultMessage .= "<b>BIN:</b> <code>".$bin."xxxxxx</code>\n";
                $resultMessage .= "<b>Request:</b> BIN Lookup\n";
                $resultMessage .= "<b>Response:</b> Declined ❌\n\n";
            }
        }

        // Add summary at the end
        $resultMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $resultMessage .= "✅ <b>Processing Complete!</b>\n";
        $resultMessage .= "★ <b>Total Checked:</b> ".count($bins)." | <b>Valid:</b> ".$validCount."\n";
        $resultMessage .= "★ <b>Checked By:</b> @".$usernam."\n\n";
        $resultMessage .= "<b>Time:</b> ".date('g:i A')."\n";
        $resultMessage .= "<b>Date:</b> ".date('d M Y')."\n\n";
        $resultMessage .= "⚡ <b>Bot By:</b> @Calv_M";

        // Delete processing message
        if ($processingMessageId) delMessage($chatId, $processingMessageId);
        sendMessage($chatId, $resultMessage, $message_id);
    } else {
        $msg = "❌ <b>Invalid Format</b>\n";
        $msg .= "★ <b>Usage:</b> /massbin 123456 234567 345678\n";
        $msg .= "★ <b>Separate BINs with:</b> spaces, commas, or pipes\n";
        $msg .= "★ <b>Max:</b> 10 BINs at once\n";
        $msg .= "\n⚡ <b>Bot By:</b> @Calv_M";
        sendMessage($chatId, $msg, $message_id);
    }
}

//////////=========[Single BIN Lookup]=========//////////
elseif ((strpos($message, "/bin") === 0)||(strpos($message, "!bin") === 0)||(strpos($message, ".bin") === 0)){
// Increment BIN check count
incrementBinChecks($userId);

$bin = substr($message, 5);
$bin = trim($bin);
if (!empty($bin) && strlen($bin) >= 6) {
$bin = substr($bin, 0, 6);

// Send processing message and capture its message_id
        $processingMsg = "⏳ <b>Processing BIN Lookup...</b>\n\n";
        $processingMsg .= "🔍 <b>Checking BIN...</b>\n";
        $processingMsg .= "🔄 <b>Please wait...</b>";

        // Send message and get response to extract message_id
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $website."/sendMessage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $chatId,
            'text' => $processingMsg,
            'reply_to_message_id' => $message_id,
            'parse_mode' => 'HTML'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $sentResponse = curl_exec($ch);
        curl_close($ch);

        $sentData = json_decode($sentResponse, true);
        $processingMessageId = $sentData['result']['message_id'] ?? null;

        // Track processing time
        $startTime = microtime(true);

// Use cached BIN lookup
$fim = lookupBin($bin);

// Calculate processing time in seconds
$processingTime = round((microtime(true) - $startTime), 3);

if ($fim !== false) {
    // Decode JSON response - handle double-encoded emojis
    $binData = json_decode($fim, true);

    if (!$binData || (!isset($binData['scheme']) && !isset($binData['type']) && !isset($binData['brand']))) {
        // Delete processing message
        if ($processingMessageId) delMessage($chatId, $processingMessageId);
        sendMessage($chatId, '<b>❌ BIN Lookup Failed</b>%0A<b>Error:</b> Invalid data received. Please try again.%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
        exit;
    }

    // Extract bank name - handle both nested and flat structures
    if (isset($binData['bank']['name']) && !empty($binData['bank']['name']) && $binData['bank']['name'] !== 'Unknown') {
        $bank = $binData['bank']['name'];
    } elseif (isset($binData['bank']) && is_string($binData['bank']) && !empty($binData['bank']) && $binData['bank'] !== 'Unknown') {
        $bank = $binData['bank'];
    } else {
        $bank = 'Unknown';
    }

    // Extract country name - handle both nested and flat structures
    if (isset($binData['country']['name'])) {
        $name = $binData['country']['name'];
    } elseif (isset($binData['country']) && is_string($binData['country'])) {
        $name = $binData['country'];
    } else {
        $name = 'Unknown';
    }

    // Extract emoji - properly decode if double-encoded
    $emoji = '';
    if (isset($binData['country']['emoji'])) {
        $emoji = $binData['country']['emoji'];
        // Fix double-encoded emojis like \\ud83c\\uddf0\\ud83c\\uddea
        if (strpos($emoji, '\\u') !== false) {
            $emoji = json_decode('"' . $emoji . '"');
        }
    }

    // Extract brand - uppercase fields from some APIs
    $brand = $binData['brand'] ?? $binData['BRAND'] ?? $binData['scheme'] ?? $binData['SCHEME'] ?? 'Unknown';
    if ($brand !== 'Unknown' && strtoupper($brand) === $brand) {
        // If all uppercase, convert to title case
        $brand = ucfirst(strtolower($brand));
    }

    // Extract scheme - handle both lowercase and uppercase
    $scheme = $binData['scheme'] ?? $binData['SCHEME'] ?? $binData['brand'] ?? $binData['BRAND'] ?? 'Unknown';
    if ($scheme !== 'Unknown' && strtoupper($scheme) === $scheme) {
        // If all uppercase, convert to title case
        $scheme = ucfirst(strtolower($scheme));
    }

    // Extract type
    $type = strtolower($binData['type'] ?? $binData['TYPE'] ?? 'unknown');

    // Determine card type
    if ($type === 'credit') {
        $bintype = 'Credit';
    } elseif ($type === 'debit') {
        $bintype = 'Debit';
    } elseif ($type === 'prepaid') {
        $bintype = 'Prepaid';
    } else {
        $bintype = 'Unknown';
    }

    // Format message - clean design like screenshot
    $msg = "<b>BIN:</b> <code>".$bin."xxxxxx</code>\n";
    $msg .= "<b>Request:</b> BIN Lookup\n";
    $msg .= "<b>Response:</b> Valid ✅\n\n";
    $msg .= "<b>Info:</b> ".strtoupper($scheme)." - ".strtoupper($bintype)." - ".strtoupper($brand)."\n";
    $msg .= "<b>Issuer:</b> ".strtoupper($bank)."\n";
    $msg .= "<b>Country:</b> ".strtoupper($name)." ".$emoji."\n\n";
    $msg .= "<b>Time:</b> ".$processingTime." seconds";

    // Delete processing message
    if ($processingMessageId) delMessage($chatId, $processingMessageId);
    sendMessage($chatId, $msg, $message_id);
} else {
    $msg = "<b>BIN:</b> <code>".$bin."xxxxxx</code>\n";
    $msg .= "<b>Request:</b> BIN Lookup\n";
    $msg .= "<b>Response:</b> Declined ❌\n\n";
    $msg .= "<b>Info:</b> Unable to retrieve BIN information\n";
    $msg .= "<b>Issuer:</b> Unknown\n";
    $msg .= "<b>Country:</b> Unknown\n\n";
    $msg .= "<b>Time:</b> ".$processingTime." seconds";

    // Delete processing message
    if ($processingMessageId) delMessage($chatId, $processingMessageId);
    sendMessage($chatId, $msg, $message_id);
}
}
else {
    $msg = "❌ <b>Invalid Bin</b>\n";
    $msg .= "<b>Format:</b> /bin xxxxxx";
    // Delete processing message if it exists
    if ($processingMessageId) delMessage($chatId, $processingMessageId);
    sendMessage($chatId, $msg, $message_id);
}
}

//////////=========[REFRESH SERVER COMMAND - OWNER ONLY]=========//////////
elseif ((strpos($message, "/refreshserver") === 0)||(strpos($message, "/restart") === 0)){
    // Check if user is the owner
    if ($userId != '6643462826') {
        sendMessage($chatId, "❌ <b>Access Denied</b>\n\nThis command is only available to the bot owner.", $message_id);
        exit;
    }

    sendMessage($chatId, "🔄 <b>Restarting PHP Server...</b>\n\nPlease wait a moment.", $message_id);

    // Kill all PHP server processes
    exec('pkill -9 -f "php -S" 2>&1');
    sleep(2);

    // Start a new PHP server in the background
    $startCmd = 'cd '.getcwd().' && nohup php -S 0.0.0.0:8080 bot.php > server.log 2>&1 & echo $!';
    exec($startCmd, $output2, $return_var2);
    sleep(2);

    // Check if server started
    $serverRunning = trim(shell_exec('pgrep -f "php -S 0.0.0.0:8080"'));

    if (!empty($serverRunning)) {
        $msg = "✅ <b>Server Restart Complete</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🔧 PHP server has been restarted.\n";
        $msg .= "🌐 Server running on port 8080 (PID: ".$serverRunning.").\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    } else {
        $msg = "⚠️ <b>Server Restart Complete</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🔧 Restart initiated successfully.\n";
        $msg .= "🌐 Server will start on next webhook request.\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ <b>Bot By:</b> @Calv_M";
    }

    sendMessage($chatId, $msg, $message_id);
}

//////////=========[BOT STATUS COMMAND - OWNER ONLY]=========//////////
elseif ((strpos($message, "/botstatus") === 0)||(strpos($message, "/status") === 0)){
    // Check if user is the owner
    if ($userId != '6643462826') {
        sendMessage($chatId, "❌ <b>Access Denied</b>\n\nThis command is only available to the bot owner.", $message_id);
        exit;
    }

    $allUsers = getAllRegisteredUsers();
    $bannedUsers = getAllBannedUsers();
    if (!$allUsers) $allUsers = [];
    if (!$bannedUsers) $bannedUsers = [];

    $totalUsers = count($allUsers);
    $totalBanned = count($bannedUsers);
    $totalChecks = 0;
    $totalBinChecks = 0;

    foreach ($allUsers as $userData) {
        $totalChecks += isset($userData['total_checks']) ? $userData['total_checks'] : 0;
        $totalBinChecks += isset($userData['bin_checks']) ? $userData['bin_checks'] : 0;
    }

    // Get server boot time
    $uptime = trim(shell_exec('uptime -s 2>/dev/null || echo ""'));
    if (empty($uptime)) {
        $uptime = date('Y-m-d H:i:s', filectime(__FILE__));
    }

    // Check if PHP server is running
    $serverStatus = trim(shell_exec('pgrep -f "php -S 0.0.0.0:8080"'));
    $serverStatusText = !empty($serverStatus) ? "✅ Running (PID: ".$serverStatus.")" : "⚠️ Not Running";

    // Get memory usage
    $memoryUsage = memory_get_usage(true) / 1024 / 1024;
    $memoryUsage = round($memoryUsage, 2);

    $msg = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📡 <b>BOT STATUS REPORT</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "🤖 <b>Bot:</b> ✅ Online\n";
    $msg .= "🌐 <b>Server:</b> ".$serverStatusText."\n";
    $msg .= "⏱️ <b>Started:</b> ".$uptime."\n";
    $msg .= "💾 <b>Memory:</b> ".$memoryUsage." MB\n";
    $msg .= "💳 <b>Stripe:</b> ".(isStripeEnabled() ? '🟢 Online' : '🔴 Offline')."\n";
    $msg .= "⚙️ <b>Maintenance:</b> ".(isMaintenanceMode() ? '🔴 Active' : '🟢 Inactive')."\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📊 <b>USER STATISTICS</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "👥 <b>Registered:</b> ".$totalUsers."\n";
    $msg .= "🚫 <b>Banned:</b> ".$totalBanned."\n";
    $msg .= "💳 <b>Card Checks:</b> ".$totalChecks."\n";
    $msg .= "🔍 <b>BIN Checks:</b> ".$totalBinChecks."\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📅 <b>Report:</b> ".date('Y-m-d H:i:s')."\n\n";
    $msg .= "⚡ <b>Bot By:</b> @Calv_M";

    sendMessage($chatId, $msg, $message_id);
}

//////////=========[SK Command]=========//////////
elseif (strpos($message, "/sk") === 0){
$sec = substr($message, 4);
if (!empty($sec)) {
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/charges');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_USERPWD, $sk. ':' . '');
curl_setopt($ch, CURLOPT_POSTFIELDS, 'amount=100&currency=usd&source=tok_us');
$result = curl_exec($ch);
$msg = GetStr($result,'"message": "','"');
if(strpos($result, '"status": "succeeded"')){
sendMessage($chatId, '<b>⋆ Status -» Live Key ✅</b>%0A<b>⋆ Response -» '.$msg.'</b>%0A<b>⋆ Key -» <code>'.$sec.'</code></b>%0A<b>⋆ Checked By:</b> @'.$usernam.'%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
}elseif(strpos($result, '"seller_message": "Payment complete."')){
sendMessage($chatId, '<b>⋆ Status -» Live Key ✅</b>%0A<b>⋆ Response -» '.$msg.'</b>%0A<b>⋆ Key -» <code>'.$sec.'</code></b>%0A<b>⋆ Checked By:</b> @'.$usernam.'%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
}elseif(strpos($result, 'code')){
sendMessage($chatId, '<b>⋆ Status -» Dead Key ❌</b>%0A<b>⋆ Response -» '.$msg.'</b>%0A<b>⋆ Key -» <code>'.$sec.'</code></b>%0A<b>⋆ Checked By:</b> @'.$usernam.'%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
}
else{
sendMessage($chatId, '<b>⋆ Status -» Error</b>%0A<b>⋆ Response -» Unknown Response</b>%0A<b>⋆ Key -» <code>'.$sec.'</code></b>%0A<b>⋆ Checked By:</b> @'.$usernam.'%0A%0A<b>⋆ Bot By: @Calv_M</b>', $message_id);
}
}else{
sendMessage($chatId, '<b>❌ Invalid Format%0AUsage: /sk sk_live_xxxxx</b>', $message_id);
}
}
?>
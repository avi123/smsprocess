<?php

// taken from http://php.net/manual/en/function.mb-split.php#80046
function mbStringToArray($string) {
    $strlen = mb_strlen($string);
    $array = [];
    while ($strlen) {
        $array[] = mb_substr($string,0,1,"UTF-8");
        $string = mb_substr($string,1,$strlen,"UTF-8");
        $strlen = mb_strlen($string);
    }
    return $array;
}

// certain emojis were stored as renderable mb chars instead of surrogate pair NCRs, so they were not replaced earlier on
// here we find all characters that are mb chars and check their hex representation - matches are manually replaced with
// their unicode values as converted on http://www.endmemo.com/unicode/unicodeconverter.php
function processEmojis($line) {

    $lineArray = mbStringToArray($line);

    foreach ($lineArray as $key => $char) {

        if (mb_strlen($char) == 1) {
            continue;
        }
        
        switch (bin2hex($char)) {
            case 'efb88f':
                $lineArray[$key] = '';
                break;
            case 'e29894':
                $lineArray[$key] = 'emoji[2614]';
                break;
            case 'e29b85':
                $lineArray[$key] = 'emoji[26c5]';
                break;
            case 'e29880':
                $lineArray[$key] = 'emoji[2600]';
                break;
            case 'e29abd':
                $lineArray[$key] = 'emoji[26bd]';
                break;
            case 'e29aa1':
                $lineArray[$key] = 'emoji[26a1]';
                break;
            case 'e29aa0':
                $lineArray[$key] = 'emoji[26a0]';
                break;
            case 'e29c85':
                $lineArray[$key] = 'emoji[2705]';
                break;
            case 'e29d93':
                $lineArray[$key] = 'emoji[2753]';
                break;
            case 'e29d97':
                $lineArray[$key] = 'emoji[2757]';
                break;
            case 'e29ca8':
                $lineArray[$key] = 'emoji[2728]';
                break;
            case 'e28fb3':
                $lineArray[$key] = 'emoji[23f3]';
                break;
            case 'e28c9b':
                $lineArray[$key] = 'emoji[231b]';
                break;
            case 'e29b84':
                $lineArray[$key] = 'emoji[26c4]';
                break;
            case 'e2ad90':
                $lineArray[$key] = 'emoji[2b50]';
                break;
            case 'e29da4':
                $lineArray[$key] = 'emoji[2764]';
                break;
            case 'e29881':
                $lineArray[$key] = 'emoji[2601]';
                break;
            case 'e29c88':
                $lineArray[$key] = 'emoji[2708]';
                break;
        }

    }

    return implode('', $lineArray);
}

set_error_handler(function ($errno, $errstr) {
    throw new Exception($errstr, $errno);
}, E_ALL);

if (php_sapi_name() == "cli") {
    $file = $argv[1];
    $me = $argv[2];
    $you = $argv[3];
} else {
    $file = $_GET['file'];
    $me = $_GET['me'];
    $you = $_GET['you'];
}

$xmlReader = new XMLReader();
$xmlReader->open($file);

$messages = [];
$singleIndex = '';

while ($xmlReader->read()) {
    // date appears to be timestamp (seconds from the epoch?), but is useful for sorting messages by time
    // readable date is in human readable format (Jul 4, 2014 10:21:10 PM)
    // type is whether I sent or received the message - note that type field is taken from SMS attribute - MMS actually
    // uses a field called msg_box.  both use a value of 2 to denote that I sent, 1 to denote that I received
    switch ($xmlReader->name) {
        case 'mms':
            $messageXml = simplexml_load_string($xmlReader->readOuterXml());
            $messageAttributes = $messageXml->attributes();

            $message = [
                'date' => (string) $messageAttributes['date'],
                'parts' => [
                    'images' => [],
                    'text' => ''
                ],
                'readableDate' => (string) $messageAttributes['readable_date'],
                'type' => (string) $messageAttributes['msg_box']
            ];

            // an mms message is a multipart message so we will process the individual parts.  for our purposes, we only
            // care about images and text - but mms can contain other data types
            foreach ($messageXml->parts as $part) {
                foreach ($part as $subpart) {
                    $ct = $subpart->attributes()['ct'];

                    switch($ct) {
                        case 'application/smil':
                            break;
                        case 'image/jpeg':
                            // images are base64 encoded and stored directly in the xml - we will just maintain base64
                            // encoding and output directly into an img tag
                            $data = (string) $subpart->attributes()['data'];
                            $message['parts']['images'][] = "<img src='data:image/jpeg;base64,$data' />";
                            break;
                        case 'text/plain':
                            $message['parts']['text'] = (string) $subpart->attributes()['text'];
                            break;
                        default:
                            echo "$ct\n";
                            break;
                    }
                }
            }

            // it appears that multiple mms tags with the same timestamp would occur, but only the first contains actual
            // message data, the result being that the message would be erased when processing the followup tags.  this
            // basically ignores the followup tags
            if (isset($messages[$message['date']])) {
                if (!empty($message['parts']['images']) || !empty($message['parts']['text'])) {
                    die('Second MMS tag contains data!');
                }
            } else {
                $messages[$message['date']] = $message;
            }
            break;
        case 'sms':
            $messageAttributes = simplexml_load_string($xmlReader->readOuterXml())->attributes();
            $body = (string) $messageAttributes['body'];

            // final round of SMS processing
            // 1) process emojis that were stored as mb unicode chars instead of NCRs
            $body = processEmojis($body);
            // 2) replace the U/S combo emoji with the american flag emoji - there are other combo emojis but this is
            // the only one that I need
            $body = str_replace('emoji[1f1fa]emoji[1f1f8]', "<img src='images/emoji/unicode/1f1fa-1f1f8.png' />", $body);
            // 3) remove what is clearly cruft
            $body = str_replace('emoji[aa]', '', $body);
            // 4) replace [newline] with whitespace
            $body = str_replace('[newline]', ' ', $body);
            // 5) remove whitespace from 4 if at the beginning or end of the line
            $body = trim($body);
            // 6) replace ALL emoji tags with their image representations
            $body = preg_replace('/emoji\[([0-9a-f]+)\]/', "<img src='images/emoji/unicode/$1.png' />", $body);
            $message = [
                'body' => $body,
                'date' => (string) $messageAttributes['date'],
                'readableDate' => (string) $messageAttributes['readable_date'],
                'type' => (string) $messageAttributes['type']
            ];
            $messages[$message['date']] = $message;
            break;
        case '#text':
            break;
        default:
            break;
    }
}

// messages are by default stored in chronological order, but in two groups, first sms, and then mms.  this reorders the
// messages so that they are in true chronological order, sms and mms together
ksort($messages);

?>

<?php // and now we render the messages into html ?>
<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <title></title>
</head>
<body>

<?php foreach ($messages as $message): ?>
    <?php if ($message['type'] == 1): ?>
        <h2><?= $you ?></h2>
    <?php else: ?>
        <h2><?= $me ?></h2>
    <?php endif; ?>

    <h3><?= $message['readableDate']; ?></h3>

    <?php if (isset($message['body'])): ?>
        <p><?= $message['body'] ?></p>
    <?php endif; ?>

    <?php if (isset($message['parts'])): ?>
        <?php foreach ($message['parts']['images'] as $image): ?>
            <?= $image ?>
        <?php endforeach; ?>

        <?php if ($message['parts']['text']): ?>
            <p><?= $message['parts']['text'] ?></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>

</body>
</html>

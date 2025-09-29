<?php
// includes/functions.php
function geocodeAddress($address) {
$apiKey = 'YOUR_GOOGLE_API_KEY'; // move to environment/config in production
$addressEnc = urlencode($address);
$url = "https://maps.googleapis.com/maps/api/geocode/json?address={$addressEnc}&key={$apiKey}";


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$resp = curl_exec($ch);
curl_close($ch);


$data = json_decode($resp, true);
if (!empty($data['results'][0]['geometry']['location'])) {
return [
'lat' => $data['results'][0]['geometry']['location']['lat'],
'lng' => $data['results'][0]['geometry']['location']['lng']
];
}
return false;
}
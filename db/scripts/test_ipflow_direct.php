<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

echo "=== IPFLOW DIRECT TOKEN TEST ===\n";

$token = "zbb7bGqneInxR6sVrNOLyCTvDIk02xMQDgoSnWRmbHBOCcME6qp7lSYki0WZj32OvbrClQqzGfIEQBNAucdI31PWbXuyLftSHiDLqn4suEcOn81DtMkLdcAtGVvq3DPi";
$url = "https://api.ipflow.com/v1.0/search/ipaddress/8.8.8.8";

echo "Testing if Client Secret works as a Bearer token...\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $res\n";

if ($code == 200) {
    echo "!!! SUCCESS !!! The secret IS the token.\n";
} else {
    echo "Failed. Code $code.\n";
}

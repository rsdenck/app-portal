<?php
require_once __DIR__ . '/../includes/bootstrap.php';

echo "=== IPFLOW GATEWAY BASIC AUTH TEST ===\n";

$clientId = "D2skCl7ixtUnPML9SMFYBQqnLwNzHy14v8psmR0oubSjqptG";
$clientSecret = "zbb7bGqneInxR6sVrNOLyCTvDIk02xMQDgoSnWRmbHBOCcME6qp7lSYki0WZj32OvbrClQqzGfIEQBNAucdI31PWbXuyLftSHiDLqn4suEcOn81DtMkLdcAtGVvq3DPi";
$personId = "8ABBD3DF0DA5EE52AB965E7F11439FB9";

$url = "https://www.apiflow.com.br/gateway/$personId/ipinfo?ip=8.8.8.8";

echo "Testing Gateway with Basic Auth...\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$clientSecret");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $res\n";

if ($code == 200) {
    echo "!!! SUCCESS !!! Gateway works with Basic Auth.\n";
} else {
    echo "Failed. Code $code.\n";
}

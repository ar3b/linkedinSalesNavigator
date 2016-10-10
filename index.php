<?php

use DiDom\Document;
require_once 'autoload.php';
require_once 'lib.php';
require_once 'auth.php';
require_once 'browser.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre>".PHP_EOL;

// Init request to get cookies and login form
$request = Requests::get(
    "https://www.linkedin.com/uas/login?goback=&trk=hb_signin",
    $_BROWSER_HEADERS
);

$cookies = array();
if ($request->status_code==200) {
    $cookies = $request->cookies;
}

l("Cookies:");
foreach ($cookies as $c) {
    r(
        $c->name,
        $c->value
    );
}
sep();

$data = $request->body;
$login_doc = new Document();
$login_doc->loadHtml($data);
$login_form = $login_doc->xpath("//form[@id='login']");
$login_url = $login_form[0]->getAttribute("action");
r("login_url", $login_url);
sep();

$login_form = $login_doc->xpath("//form[@id='login']//input");
$params = array();
foreach ($login_form as $input) {
    $params[$input->getAttribute("name")] =  $input->getAttribute("value");
}

$params["session_key"] = $_AUTH_LOGIN;
$params["session_password"] = $_AUTH_PASSWORD;
$params["client_n"] = "";
$options["cookies"] = $cookies;

$login_headers = array_merge(
    $_BROWSER_HEADERS,
    array(
        "Content-Type" => "application/x-www-form-urlencoded",
        "Referer" => "https://www.linkedin.com/",
        "X-IsAJAXForm" => "1",
        "X-Requested-With" => "XMLHttpRequest",
    )
);
l("Params:");
foreach ($params as $key=>$param) {
    r(
        $key,
        $param
    );
}
sep();

// Login request
$request = Requests::post(
    $login_url,
    $login_headers,
    $params,
    array(
        "cookies" => $cookies,
    )
);
l("Response:");
r("Login status code", $request->status_code);
if ($request->status_code!=200) {
    die();
}
$body = json_decode($request->body, true);
r("Body", $request->body);
r("Redirect url", $body["redirectUrl"]);
l("New cookies:");
$cookies = $request->cookies;
foreach ($cookies as $c) {
    r(
        $c->name,
        $c->value
    );
}
sep();

// Redirecting to 'redirect url'

$redirect_headers = array_merge(
    $_BROWSER_HEADERS,
    array(
        "Referer" => "https://www.linkedin.com/uas/login?goback=&trk=hb_signin",
    )
);

$request = Requests::get(
    $body["redirectUrl"],
    $redirect_headers,
    array(
        "cookies" => $cookies,
    )
);

r("Redirect status code", $request->status_code);

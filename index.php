<?php

use DiDom\Document;
require_once 'autoload.php';
require_once 'lib.php';
require_once 'auth.php';
require_once 'browser.php';

$_NEED_LOGGEN_IN  = false;
$_COOKIES_FILE = "temp/session.txt";

function check_is_logged_in($url, &$cookies, $headers) {
    $request = Requests::get(
        $url,
        $headers,
        array(
            "cookies" => $cookies,
        )
    );

    r("Redirect status code", $request->status_code);

    // Checking is logged in

    $logged_doc = new Document();
    $logged_doc->loadHtml($request->body);
    $logout_link = $logged_doc->xpath("//a[contains(@href, 'https://www.linkedin.com/uas/logout')]");
    $cookies = $request->cookies;
    if (count($logout_link) == 0) {
        r("Logged in check", "FAIL");
    } else {
        r("Logged in check", "Ok");
    }
    sep();
    return (count($logout_link) != 0);
}

header('Content-Type: text/html; charset=utf-8');
echo "<pre>".PHP_EOL;

$cookies = array();
if ((!$_NEED_LOGGEN_IN) and file_exists($_COOKIES_FILE)) {
    $cookies = unserialize(file_get_contents($_COOKIES_FILE));
    $_NEED_LOGGEN_IN = !check_is_logged_in(
        "https://www.linkedin.com/",
        $cookies,
        $_BROWSER_HEADERS
    );
}

if (($_NEED_LOGGEN_IN) or (!file_exists($_COOKIES_FILE))) {

    // Init request to get cookies and login form
    $request = Requests::get(
        "https://www.linkedin.com/uas/login?goback=&trk=hb_signin",
        $_BROWSER_HEADERS
    );

    $cookies = array();
    if ($request->status_code == 200) {
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
        $params[$input->getAttribute("name")] = $input->getAttribute("value");
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
    foreach ($params as $key => $param) {
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
    if ($request->status_code != 200) {
        die();
    }
    $body = json_decode($request->body, true);
    r("Body", $request->body);
    if ($body["status"] != "ok") {
        r("Login status", "FAIL");
        die();
    }

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

    check_is_logged_in($body["redirectUrl"], $cookies, $redirect_headers);

    $cookies = $request->cookies;
    file_put_contents($_COOKIES_FILE, serialize($cookies));

}

// Checking is sales page

$request = Requests::get(
   "https://www.linkedin.com/sales/?trk=sn_nav2__logo",
    $_BROWSER_HEADERS,
    array(
        "cookies" => $cookies,
    )
);

$sales_doc = new Document();
$sales_doc->loadHtml($request->body);
$body = $sales_doc->xpath("//body[contains(@id, 'pagekey-sales-home')]");
if (count($body)==0) {
    r("Sales check", "FAIL");
    die();
} else {
    r("Sales check", "Ok");
}

sep();

// Search first page request

$request = Requests::get(
    "https://www.linkedin.com/sales/search?keywords=apple&count=25&start=25",
    $_BROWSER_HEADERS,
    array(
        "cookies" => $cookies,
    )
);

// First members results
$search_doc = $request->body;
$cookies = $request->cookies;
$result = array();
if (!preg_match('/\<code id=\"embedded\-json\"\>\<!\-\-(.*?)\-\-\>\<\/code\>/is', $search_doc, $result)) {
    r("Embedded json search", "FAIL");
    die();
}

$result = json_decode($result[1], true);

// Additional info request
foreach ($result["searchResults"] as $member) {
    $company = $member["company"];
    $member = $member["member"];
    $url = "https://www.linkedin.com/sales/profile/".$member["memberId"].",".$member["authToken"].",".$member["authType"];
    r("Name", $member["formattedName"]);
    r("Title", htmlspecialchars_decode($member["title"]));
    r("Company", strip_tags($company["companyName"]));
    r("Location", $member["location"]);
    r("Sales profile url", $url);
    $request = Requests::get(
        $url,
        $_BROWSER_HEADERS,
        array(
            "cookies" => $cookies,
        )
    );

    $member_doc = $request->body;

    $result = array();
    if (!preg_match('/\<code id=\"embedded\-json\"\>\<!\-\-(.*?)\-\-\>\<\/code\>/is', $member_doc, $result)) {
        r("Embedded json search", "FAIL");
        die();
    }

    $result = json_decode($result[1], true);

    if (isset($result["profile"]["contactInfo"]["publicProfileUrl"])) {
        r("Public url", $result["profile"]["contactInfo"]["publicProfileUrl"]);
    }
    if (isset($result["profile"]["contactInfo"]["twitterAccounts"])) {
        foreach ($result["profile"]["contactInfo"]["twitterAccounts"] as $twitter) {
            r("Twitter", "https://www.twitter.com/".$twitter);
        }
    }
    if (isset($result["profile"]["contactInfo"]["emails"])) {
        foreach ($result["profile"]["contactInfo"]["emails"] as $email) {
            r("Email", $email);
        }
    }

    sep();
}
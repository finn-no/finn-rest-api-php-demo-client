<?php
/********************************************************************
 *
 * Simple api-client demo for "Boliger til salgs" search
 *
 * This demo aims to get you started, it comes with absolutely NO GUARANTEE!
 * As an API-client you are responsible your client-side code
 *
 ********************************************************************/

header('Content-Type: text/html; charset=utf-8');

//parse client.ini
$clientIniArray = parse_ini_file("../../config/client.ini");
$apiKey = $clientIniArray['apiKey'];
$orgId = $clientIniArray['orgId'];
$userAgent = $clientIniArray['userAgent'];

//urlencode: "..q=" . urlencode("mjøsa");
//location=0.20061 == Oslo
$apiUrl = "https://cache.api.finn.no/iad/search/realestate-homes?orgId=$orgId&page=1&rows=100&location=0.20061";

//fetch using curl (or whatever you prefer, curl is good for setting custom headers)
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_URL, utf8_decode($apiUrl));
curl_setopt($ch, CURLOPT_HTTPHEADER, array("x-finn-apikey: $apiKey"));
$rawData = curl_exec($ch);
if (curl_error($ch)) {
    die("Fetch problem");
}

//parse the xml and get namespaces (needed later to extract attributes and values)
$xmlData = new SimpleXMLElement($rawData);
$ns = $xmlData->getNamespaces(true);

//search data:
$searchTitle = $xmlData->title;
$searchSubTitle = $xmlData->subtitle;
$searchTotalResults = $xmlData->children($ns['os'])->totalResults;

//navigation links
$links = array();
foreach ($xmlData->link as $link) {
    $rel = $link->attributes()->rel;
    $ref = $link->attributes()->href;
    $links["$rel"] = "$ref";
}

//debug
echo "
Search title    => $searchTitle
Search subtitle => $searchSubTitle
Search results  => $searchTotalResults
Self link:      => " . $links['self'] . "
First link:     => " . $links['first'] . "
Last link:      => " . $links['last'] . "
Next page:      => " . $links['next'] . "
Seach descr.:   => " . $links['search'] . "
";


//entry data

//get each entry for simpler syntax when looping through them later
$entries = array();
foreach ($xmlData->entry as $entry) {
    array_push($entries, $entry);
}

foreach ($entries as $entry) {
    $id = $entry->children($ns['dc'])->identifier;
    $title = $entry->title;
    $updated = $entry->updated;
    $published = $entry->published;

    $isPrivate = "false";  
    $status = "";
    $adType = "";
    foreach ($entry->category as $category) {
      if ($category->attributes()->scheme =="urn:finn:ad:private"){
        $isPrivate = $category->attributes()->term;
      }
      //if disposed == true, show the label
      if ($category->attributes()->scheme =="urn:finn:ad:disposed"){
        if($entry->category->attributes()->term == "true"){
          $status = $category->attributes()->label;
        }
      }
      if ($category->attributes()->scheme =="urn:finn:ad:type"){
        $adType = $category->attributes()->label;
      }
    }
    
    $georss = $entry->children($ns['georss'])->point;
    $location = $entry->children($ns['finn'])->location;
    $city = $location->children($ns['finn'])->city;
    $address = $location->children($ns['finn'])->address;
    $postalCode = $location->children($ns['finn'])->{'postal-code'};

    unset($img);
    if ($entry->children($ns['media']) && $entry->children($ns['media'])->content->attributes()) {
        $img = $entry->children($ns['media'])->content->attributes();
    }

    $author = $entry->author->name;

    $adata = $entry->children($ns['finn'])->adata;
    $livingSizeFrom = 0;
    $livingSizeTo = 0;
    $propertyType = "";
    $numberOfBedrooms = 0;
    $ownershipType = "";
    $usableSize = "";
    $primarySize = "";
    foreach ($adata->children($ns['finn'])->field as $field) {
        if ($field->attributes()->name == 'no_of_bedrooms') {
            $numberOfBedrooms = $field->attributes()->value;
        }
        if ($field->attributes()->name == 'property_type') {
            $propertyType = $field->children($ns['finn'])->value;
        }
        if ($field->attributes()->name == 'ownership_type') {
            $ownershipType = $field->attributes()->value;
        }
        if ($field->attributes()->name == 'size') {
            foreach ($field->children($ns['finn'])->field as $sizeField) {
                if ($sizeField->attributes()->name == "usable") {
                    $usableSize = $sizeField->attributes()->value;
                }
                if ($sizeField->attributes()->name == "primary") {
                    $primarySize = $sizeField->attributes()->value;
                }
                $livingSizeFrom = $sizeField->attributes()->from;
                $livingSizeTo = $sizeField->attributes()->to;
            }
        }
    }

    $mainPrice = "";
    $totalPrice = "";
    $collectiveDebt = "";
    $sharedCost = "";
    $estimatedValue = "";
    $sqmPrice = "";
    foreach ($adata->children($ns['finn'])->price as $price) {
        if ($price->attributes()->name == 'main') {
            $mainPrice = $price->attributes()->value;
        }
        if ($price->attributes()->name == 'total') {
            $totalPrice = $price->attributes()->value;
        }
        if ($price->attributes()->name == 'collective_debt') {
            $collectiveDebt = $price->attributes()->value;
        }
        if ($price->attributes()->name == 'shared_cost') {
            $sharedCost = $price->attributes()->value;
        }
        if ($price->attributes()->name == 'estimated_value') {
            $estimatedValue = $price->attributes()->value;
        }
        if ($price->attributes()->name == 'square_meter') {
            $sqmPrice = $price->attributes()->value;
        }
    }

    //debug
    echo "
    ID              => $id
    Title           => $title
    Updated         => $updated
    Published       => $published
    Is Private      => $isPrivate
    Status          => $status
    Ad Type         => $adType
    Geo             => $georss
    Address         => $address
    City            => $city
    Postal code     => $postalCode
    Author          => $author
    Bedrooms        => $numberOfBedrooms
    Ownership Type  => $ownershipType
    Usable Size     => $usableSize
    Primary Size    => $primarySize
    Size from       => $livingSizeFrom
    Size to         => $livingSizeTo
    Main Price      => $mainPrice
    Total Price     => $totalPrice
    Collective Debt => $collectiveDebt
    Shared Cost     => $sharedCost
    Estimated Value => $estimatedValue
    Price pr. M2    => $sqmPrice
    Property Type   => $propertyType
    Image URL       => " . $img->url . "
    ";
}
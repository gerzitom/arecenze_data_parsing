<?php
/**
 * User: tomasgerzicak
 * Date: 07/05/2020
 */

include_once "simple_html_dom.php";

ini_set("memory_limit", "100000M");

// Připojovací údaje
define('SQL_HOST', 'localhost:8889');
define('SQL_DBNAME', 'acka');
define('SQL_USERNAME', 'root');
define('SQL_PASSWORD', 'root');

$dsn = 'mysql:dbname=' . SQL_DBNAME . ';host=' . SQL_HOST . '';
$user = SQL_USERNAME;
$password = SQL_PASSWORD;

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

    $dotaz = $pdo->query("SELECT meta_value, meta_key, post_id FROM wp_postmeta WHERE meta_key='crs_how_to_choose' AND post_id=17682");
    $rows = $dotaz->fetchAll();

    $validPosts = 0;


    $uncompiledPosts = [];
    $links = [];
    foreach ($rows as $row){
        $parser = str_get_html($row['meta_value']);
        $sourcesFound = false;
        if($parser){
            $validPosts++;
            foreach ($parser->find("div") as $element){
                if($element){

                    foreach ($element->find("p") as $heading){
//                        if($heading->innertext == "Použité a další zajímavé zdroje:"){
                        if(isHeadingName($heading->innertext)){
                            $sourcesFound = true;
                            $sourcesContent = "";

                            // position where sources starts
                            $sourcesPosStart = strpos($row['meta_value'], $heading->outertext);

                            // sources html string
                            $sourcesData = substr($row['meta_value'], $sourcesPosStart  + strlen($heading->outertext));

                            // content stripped out from sources
                            $contentWithoutSources = substr($row['meta_value'], 0, $sourcesPosStart);

                            // parse sources
                            $parsedSources = str_get_html($sourcesData);
                            $parsedLinks = [];
                            foreach ($parsedSources->find('a') as $link){
                                $parsedLinks[] = '<p>'.$link.'</p>';
                            }
                            $links[] = array(
                                "post_id" => $row["post_id"],
                                "links" => $parsedLinks
                            );

                            // update content without sources
                            $update = "UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND (meta_key='crs_how_to_choose' OR meta_key='_crs_how_to_choose')";
                            $preparedUpdate = $pdo->prepare($update);
                            $executedUpdate = $preparedUpdate->execute([$contentWithoutSources, $row["post_id"]]);
                        }
                    }
                }
            }
            // content cant be parsed
            if(!$sourcesFound) {
                foreach (getHeadingPossibleNames() as $heading){
                    $indexOfHeading = strpos($row['meta_value'], $heading);
                    if($indexOfHeading){
                        $sourcesFound = true;
                        $sourcesString = substr($row['meta_value'], $indexOfHeading + strlen($heading));
                        $sources = str_get_html($sourcesString);
                        $itemLinks = [];
                        foreach ($sources->find("a") as $link){
                            $itemLinks[] = $link->outertext;
                        }

                        $links[] = array(
                            "post_id" => $row["post_id"],
                            "links" => $itemLinks
                        );

                        // position where sources starts
                        $sourcesPosStart = strpos($row['meta_value'], $heading);

                        // sources html string
                        $sourcesData = substr($row['meta_value'], $sourcesPosStart  + strlen($heading));

                        // content stripped out from sources
                        $contentWithoutSources = substr($row['meta_value'], 0, $sourcesPosStart);

                        // update content without sources
                        $update = "UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND (meta_key='crs_how_to_choose' OR meta_key='_crs_how_to_choose')";
                        $preparedUpdate = $pdo->prepare($update);
                        $executedUpdate = $preparedUpdate->execute([$contentWithoutSources, $row["post_id"]]);
                    }
                }
            }
            if(!$sourcesFound){
                $uncompiledPosts[] = $row["post_id"];
            }
        }
//
    }

    echo "Count: ".count($rows)."\n";
    echo "Valid posts: ".$validPosts."\n";
    echo "Parsed links: ".count($links)."\n";

    $file = fopen("resources.json", "w");
    fwrite($file, json_encode($links));
    fclose($file);

//    echo "UNCOMPILED POSTS:";
//    var_export($uncompiledPosts);



} catch (PDOException $e) {
    echo $e->getMessage();
    die('Connection failed: ' . $e->getMessage());
}


function getHeadingPossibleNames(){
    return ["Použité zdroje:", "Použité a další zajímavé zdroje:", "Použité a další zajímavé zdroje", "Použité zdroje"];
}

function isHeadingName($text){
    return in_array($text, getHeadingPossibleNames());
}
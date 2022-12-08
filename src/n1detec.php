<?php
require_once "vendor/autoload.php";

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;


$app_scan_folder = __DIR__ . "\\app";
$RELATION_SIGNATURES = [
    "hasOne",
    "belongsTo",
    "hasMany",
    "hasOneThrough",
    "hasManyThrough",
];


$FILES = [];
$RELATIONS_FOUND = [];
$all_files_scanned_ = 0;
$relations_files_scanned_ = 0;
$relations_files_compared_ = 0;
echo "\nWe found $all_files_scanned_ files for scan:...";
get_all_directory_and_files($app_scan_folder);
echo "\rWe found $all_files_scanned_ files for scan: DONE!\n";

$file_count_ = count($FILES);

// FIND all relations
echo "\nSearching relations in models: 0/$file_count_, found 0";
foreach ($FILES as $file){
    $f_content = file_get_contents($file);
    $tokens = token_get_all($f_content);
    $relations_files_scanned_++;


    $is_function_opened = false;
    $function_scope = "";
    for ($i = 0; $i < count($tokens); $i++){
        if (!is_array($tokens[$i])) continue;

//        echo "Line {$tokens[$i][2]}: ", token_name($tokens[$i][0]), " ('{$tokens[$i][1]}')", PHP_EOL;

        list($id, $text) = $tokens[$i];

        $functions_list = [];
        switch ($id) {
            case T_FUNCTION:
                $function_scope = ""; // If function found refresh function-scope

                break;
            case T_STRING:
                if($tokens[$i-2][0] === T_FUNCTION) // Function detected
                {
                    $functions_list[] = $text; // Add func name to list
                    $function_scope = $text;
//                    echo "\n\tFUNCTION NAME IS $text\n";
                }
                if(!empty($function_scope)){  // if we still inside of function scope
                    if (in_array($text, $RELATION_SIGNATURES)){ // Relation found
                        $RELATIONS_FOUND[] = [
                            "file" => $file,
                            "line" => $tokens[$i][2],
                            "signature" => $text,
                            "type" => $id,
                            "function_name" => $function_scope
                        ];
                    }
                }
                break;
            default:
                // dont touch now
                break;
        }
    }
    echo "\rSearching relations in models: $relations_files_scanned_/$file_count_, found " . count($RELATIONS_FOUND);
}

foreach ($FILES as $file){ // searching exactly N+1
    $f_content = file_get_contents($file);
    $tokens = token_get_all($f_content);
    $relations_files_compared_++;
    $found_using = [];
    foreach ($RELATIONS_FOUND as $RELATION){
        // Check if one of relation name found in file content
        if(strpos($f_content, $RELATION['function_name']) !== false){
            $found_using[] = $RELATION['function_name'];
        }
    }

    // SKIP THIS FILE IF NO MATCHES BY RELATIONS
    if(empty($found_using)) continue;

    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    try {
        $ast = $parser->parse($f_content);
    } catch (Error $error) {
        echo "Parse error: {$error->getMessage()}\n";
        return;
    }

    /**
     * @var Node[]
     */
    $matched_relation_nodes = [];

    $nodeFinder = new NodeFinder;
    $class = $nodeFinder->find($ast, function(Node $node) use ($found_using, &$matched_relation_nodes){
        if($node instanceof Node\Stmt\Foreach_){

            $relationFinder = new NodeFinder;
            $relation_class = $relationFinder->find($node, function(Node $node) use ($found_using){
                return $node instanceof PhpParser\Node\Identifier and in_array($node->name, $found_using);
            });

            if(!empty($relation_class)){
                $matched_relation_nodes[] = $relation_class;
            }

        }

        return false;
    });


    foreach ($matched_relation_nodes as $relation_node){
        echo "\n\tHello bad developer, N+1 detected: " . $relation_node[0]->getStartLine() . "-"  . $relation_node[0]->getEndLine() . " | " . $relation_node[0]->getType() . "(".$relation_node[0]->name.")";
    }
}



function get_all_directory_and_files($dir){
    global $FILES, $all_files_scanned_;
    $dh = new DirectoryIterator($dir);
    foreach ($dh as $item) {
        if (!$item->isDot()) {
            if ($item->isDir()) {
                get_all_directory_and_files("$dir/$item");
            } else {
                $all_files_scanned_++;
                echo "\rWe found $all_files_scanned_ files for scan:...";
                if(str_contains("$dir/$item", "Model"))
                    $FILES[] = "$dir/$item";//$item->getFilename();
            }
        }
    }
}

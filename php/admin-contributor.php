<?php    
    session_start();
    if (!isset($_SESSION["id"])) {
        $_SESSION["id"] = null;
    }
    if (!isset($_SESSION["isAdmin"])) {
        $_SESSION["isAdmin"] = false;
    }
    if (!isset($_SESSION["isContributor"])) {
        $_SESSION["isContributor"] = false;
    }
    if (!isset($_SESSION["language"])) {
        $_SESSION["language"] = "English";
    }

    if (is_ajax()) {
        if (isset($_POST["action"]) && !empty($_POST["action"])) {
            $action = $_POST["action"];
            switch($action) { //Switch case for value of action
                case "getData": getData(); break;
                case "updateData": updataData(); break;
                case "updateContribution": updateContribution(); break;
                case "updateContributor": updateContributor(); break;
                case "isAdmin": isAdmin(); break;
                case "isContributor": isContributor(); break;
                case "login": login(); break;
                case "logout": logout(); break;
                case "changeLanguage": changeLanguage(); break;
                case "getLanguage": getLanguage(); break;
                case "getContributorLanguage": getContributorLanguage(); break;
                case "getNumberOfContributions": getNumberOfContributions(); break;
                case "getAllTableOfThisLanguage": getTableOfLanguage(); break;
                case "getContributors": getContributors(); break;
                case "newContributor": newContributor(); break;
                case "deleteContributor" : deleteContributor(); break;
                case "approveContribution": approveContribution(); break;
                case "rejectContribution": rejectContribution(); break;
                case "getRegistrations": getRegistrations(); break;
                case "approveRegistration": approveRegistration(); break;
                case "deleteRegistration": deleteRegistration(); break;
            }
        }
    }

    function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    $db;
    function setupDatabase() {
        global $db;
        require_once '../../../config.php'; 
        $db = new mysqli(db_host, db_uid, db_pwd, db_name);
        if ($db->connect_errno) {
            exit("Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error); 
        }
    }

    function getLanguage() {
        echo $_SESSION["language"];
    }

    function getContributorLanguage() {
        echo $_SESSION["languageContributor"];
    }

    function changeLanguage() {
        $_SESSION["language"] = $_POST["language"];
    }

    function getUserId() {
        echo $_SESSION["id"];
    }

    function isAdmin() {
        if ($_SESSION["isAdmin"]) {
            echo $_SESSION["id"];
        } else {
            echo "";
        }
    }

    function isContributor() {
        if ($_SESSION["isContributor"]) {
            echo $_SESSION["id"];
        } else {
            echo "";
        }
    }

    function login() {
        global $db;
        setupDatabase();
        
        $id = $_POST["id"];
        $pw = crypt($_POST["pw"], "CRYPT_MD5");

        if (checkUser("admin", $id, $pw)) {
            $_SESSION["isAdmin"] = true;
            $_SESSION["id"] = $id;
            echo "admin";
        } else if (checkUser("contributor", $id, $pw)) {
            $_SESSION["isContributor"] = true;
            $_SESSION["id"] = $id;

            $query = "select language from contributor where username = '".$id."'";

            if ($res = $db->query($query)) {
                $row = mysqli_fetch_row($res);
                $_SESSION["languageContributor"] = $row[0];
            }

            echo "contributor";
        } else {
            echo $pw;
            echo "wrong UserName or Password";
        }
    }

    function logout() {
        $_SESSION["id"] = null;
        $_SESSION["isAdmin"] = false;
        $_SESSION["isContributor"] = false;
    }

    function checkUser($tableName, $id, $pw) {
        global $db;
        setupDatabase();
        $query = "select * from ".$tableName." where binary username = '".$id."'";
        $res = $db->query($query);
        if ($res = $db->query($query)) {
            $row = mysqli_fetch_row($res);
            if (!is_null($row) && $row[1] == $pw) {
                return true;
            }
        }
        return false;
    }

    function cleanInput($input) {
     
      $search = array(
        '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
        '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
        '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
        '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
      );
     
        $output = preg_replace($search, '', $input);
        return $output;
    }

    function newContributor() {
        global $db;
        setupDatabase();

        $id = $_POST["id"];
        $pw = crypt($_POST["pw"], "CRYPT_MD5");
        $lang = $_POST["lang"];

        $input  = cleanInput($id);
        $id = $db->real_escape_string($input);

        $input  = cleanInput($lang);
        $lang = $db->real_escape_string($input);

        if($id != null){
            $query = "insert into contributor values ('".$id."', '".$pw."', '".$lang."')";
        }
        if ($db->query($query)) {
            ///$languages = array("Vietnamese", "Chinese", "Indonesian");
            $query = "create table ".$lang."_".$id." (id INT PRIMARY KEY, content VARCHAR(500), status VARCHAR(32))";
            $db->query($query);
            /*
            for($i = 0; $i < 3; $i++) {
                $query = "create table ".$languages[$i]."_".$id." (id INT PRIMARY KEY, content VARCHAR(500))";
                $db->query($query);
            }*/
        } else {
        }
    }

    function deleteContributor() {
        global $db;
        setupDatabase(); 
        $id = $_POST["id"];
        if($id != null){
            $query = "delete FROM contributor WHERE username='" .$id. "'";
        }
        if($db->query($query)){
            $languages = array("Vietnamese", "Chinese", "Indonesian");
            for($i = 0; $i < 3; $i++) {
                $query = "drop table ".$languages[$i]."_".$id;
                $db->query($query);
            }
        }
    }

    function updateContribution() {
        global $db;
        setupDatabase();

        $tableName = $_POST["language"]."_".$_SESSION["id"];
        $data = json_decode($_POST["data"]);
        for ($i = 0; $i < count($data); $i++) {
            $input  = cleanInput($data[$i][1]);
            $id = $db->real_escape_string($input);
            if($id != null){
                updateDatabase($tableName, $data[$i][0], $id);
            }
        }
    }

    function getContributors() {
        if (!$_SESSION["isAdmin"]) {
            return;
        }

        $result = array();
        $table = getTableFromDatabase("contributor");
        while ($row = mysqli_fetch_row($table)) {
            $result[] = array($row[0], $row[2]);
        }
        echo json_encode($result);
    }

    function getNumberOfContributions() {
        global $db;
        setupDatabase();

        $language = $_POST["language"];

        $query = "show tables like '".$language."%'";
        $table = $db->query($query);
        $count = 0;
        while ($row = mysqli_fetch_row($table)) {
            $subquery = "select count(*) from ".$row[0]." where status = 'Pending'";
            $result = $db->query($subquery);
            $subrow = mysqli_fetch_row($result);
            $count = $count + $subrow[0];
        }
        echo json_encode($count);
    }

    function getData() {
        if (!$_SESSION["isAdmin"] && !$_SESSION["isContributor"]) {
            return;
        }

        $output = array();
        $name = $_POST["language"] ."_". $_SESSION["id"];
        $tableName = array("English", $_POST["language"], $name);

        for($i = 0; $i < 3; $i++) {
            $result = array();
            $table = getTableFromDatabase($tableName[$i]);
            while ($row = mysqli_fetch_row($table)) {
                if($i == 2){
                    $result[] = array($row[0], $row[1], $row[2]);
                }else{
                    $result[] = array($row[0], $row[1]);
                }
                ///$result[] = array($_POST["language"], $_SESSION["id"]);
            }
            $output[$i] = $result;
        }

        echo json_encode($output);
    }

    function getTableOfLanguage() {
        if (!$_SESSION["isAdmin"]) {
            return;
        }

        $result = array();
        $result["?Contributors"] = array();

        $language = $_POST["language"];
        $contributors = getTableFromDatabase("contributor");
        
        while ($row = mysqli_fetch_row($contributors)) {
            $tableName = $language."_".$row[0];
            $contribution = getTableFromDatabase($tableName);

            if (mysqli_num_rows($contribution) > 0)   {
                $result["?Contributors"][] = $row[0];

                $contributionArr = array();
                while ($row2 = mysqli_fetch_row($contribution)) {
                    if($row2[2] == 'Pending'){
                        $contributionArr[] = array($row2[0], $row2[1]);
                    }
                }

                $result[$row[0]] = $contributionArr;
            }
        }

        $result["?English"] = array();
        $englishTable = getTableFromDatabase("English");
        while($row = mysqli_fetch_row($englishTable)) {
            $result["?English"][] = array($row[0], $row[1]);
        }

        echo json_encode($result);
    }

    function getTableFromDatabase($tableName) {
        global $db;
        setupDatabase();

        $query = "Select * from ".$tableName;
        return $db -> query($query);
    }

    function getContentFromDatabase($tableName, $id) {
        global $db;
        setupDatabase();

        $query = "Select content from ".$tableName." where id=".$id;
        $row = mysqli_fetch_row($db -> query($query));
        if (is_null($row)) {
            return null;
        } else {
            return $row[0];
        }
    }

    function updateDatabase($tableName, $id, $content) {
        global $db;
        setupDatabase();

        $query = "insert into ".$tableName." values (".$id.", '".$content."', 'Pending')";
        if (!($db -> query($query))) {
            $text = "select * from " .$tableName. " where id=".$id;
            $row = mysqli_fetch_row($db -> query($text));
            if($row[1] != $content){
                $query = "update ".$tableName." set content='".$content."', status='Pending' where id=".$id;
                $db -> query($query);
            }
        }
    }

    function approveContribution() {
        global $db;
        setupDatabase();

        $tableName = $_POST["language"];
            $id = $_POST["id"];
            $contributor = $_POST["contributor"];

            $query = "Select content from ".$tableName."_".$contributor." where id=".$id;
            $row = mysqli_fetch_row($db -> query($query));
            $content = $row[0];

            $query = "update ".$tableName." set content='".$content."' where id=".$id;
            $db -> query($query);

            $query = "update ".$tableName."_".$contributor." set status='Approved' where id=".$id;
            $db -> query($query);

        
        // if (isAdmin() != "") {
        //     $tableName = $_POST["language"];
        //     $id = $_POST["id"];
        //     $contributor = $_POST["contributor"];

        //     $query = "Select content from ".$tableName."_".$contributor." where id=".$id;
        //     $row = mysqli_fetch_row($db -> query($query));
        //     $content = $row[0];

        //     $query = "update ".$tableName." set content='".$content."' where id=".$id;
        //     $db -> query($query);


        // }
    }

    function rejectContribution() {
        global $db;
        setupDatabase();

        $tableName = $_POST["language"];
            $id = $_POST["id"];
            $contributor = $_POST["contributor"];

            $query = "update ".$tableName."_".$contributor." set status='Rejected' where id=".$id;
            $db -> query($query);
        
        // if (isAdmin() != "") {
        //     $tableName = $_POST["language"];
        //     $id = $_POST["id"];
        //     $contributor = $_POST["contributor"];

        //     $query = "delete from ".$tableName."_".$contributor." where id=".$id;
        //     $db -> query($query);


        // }
    }

    function getRegistrations() {
        if (!$_SESSION["isAdmin"]) {
            return;
        }

        $result = array();
        $table = getTableFromDatabase("registration");
        while ($row = mysqli_fetch_row($table)) {
            $result[] = array($row[0], $row[1], $row[2], $row[3], $row[4]);
        }
        echo json_encode($result);
    }

    function deleteRegistration() {
        global $db;
        setupDatabase(); 
        $id = $_POST["id"];
        if($id != null){
            $query = "delete FROM registration WHERE username='" .$id. "'";
            $db->query($query);
        }
    }

    function approveRegistration() {
        global $db;
        setupDatabase(); 
        $id = $_POST["id"];
        $pw = crypt($_POST["pw"], "CRYPT_MD5");
        if($id != null){
            $query = "select * FROM registration WHERE username='" .$id. "'";
            $result = $db->query($query);

            $row = mysqli_fetch_row($result);


            $query = "insert into contributor values ('".$id."', '".$pw."', '".$row[3]."')";
            
            if ($db->query($query)) {
                $query = "create table ".$row[3]."_".$id." (id INT PRIMARY KEY, content VARCHAR(500), status VARCHAR(32))";
                $db->query($query);

                $query = "delete FROM registration WHERE username='" .$id. "'";
                $db->query($query);
            }
        }
    }
?>
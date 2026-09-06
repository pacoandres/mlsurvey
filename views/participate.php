<?php
require_once 'ifaces/view.php';
require_once 'utils/dbutils.php';
require_once 'views/response_survey.php';
require_once 'utils/participation.php';
require_once 'utils/token.php';
require_once 'utils/crypt.php';

class Participate extends View {
    public const ACTION = "Enviar";

    //This const should be remove in non alpha versions.
    private const TESTID = 0;

    private const COOKIE_KEY ="lacookie";
    private $key = "";
    function doInit (){
        startSession ();
        $this->key = random_bytes (32);
        $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        setcookie (self::COOKIE_KEY, base64_encode ($this->key), time () + 1800, //Half an hour
            "", $host, true, true);
    }
    function getMenuGroup (){
        return ML_MENU_GROUP_SURVEYS;
    }

    public function loadStyles (){
        ?>
        <link href="css/button3.css" rel="stylesheet" />
        <link href="css/questions.css" rel="stylesheet" />
        <?php
    }

    public function show (){
        startSession ();
        if (isset ($_REQUEST[self::ACTION])){
            $this->insertResponse ();
            return;
        }

        if (!isset ($_REQUEST[ResponseSurvey::ACTION])){
            showMain ();
            return;
        }
        if (!checkToken ()){
            tokenError ();
            return;
        }

        $surveyid = $_SESSION['surveyid'];
        $email = $_REQUEST['email'];
        $code = $_REQUEST['code'];
        clearSessionVariables ();
        unset ($_REQUEST['email']);
        unset ($_REQUEST['code']);

        $hashmail = hash ('sha256', $email);

        try {
            $db = dbConn ();
            //This block should be removed in non alpha versions
            if ($email == "prueba@mierda.cow" && $code = "123456"){
                $_SESSION['privkey'] = "no";
                $_SESSION['surveyid'] = $surveyid;
                $_SESSION['participantid'] = self::TESTID;
                $this->showSurvey ($db, $surveyid);
                return;
            }


            $participants = $db->prepare ("SELECT participantid, privatekey " .
                "FROM {Participants} WHERE participant = :part");
            $participants->bindParam (":part", $hashmail, PDO::PARAM_STR);
            $participants->execute ();
            if ($participants->rowCount () == 0){
                $this->securityError ();
                return;
            }
            $participant = $participants->fetch ();
            $participantid = $participant["participantid"];
            $privatekeycryp = $participant['privatekey'];
            $participants->closeCursor ();
            if (hasParticipated ($db, $participantid, $surveyid)){
                ?>
                <p><strong>Ya se ha participado en la consulta desde la dirección de correo
                    indicada.
                </strong></p>
                <?php
                return;
            }
            $codes = $db->prepare ("SELECT passwd FROM {Participation} WHERE " .
                "participantid = :pid AND surveyid = :sid");
            $codes->bindParam (":pid", $participantid, PDO::PARAM_INT);
            $codes->bindParam (":sid", $surveyid, PDO::PARAM_INT);
            $codes->execute ();
            if ($codes->rowCount () == 0){
                ?>
                <p><strong>La dirección de correo indicada no ha solicitado código para
                    participar</strong></p>
                <?php
                return;
            }
            $codecrypted = ($codes->fetch())['passwd'];
            $codes->closeCursor ();
            if (!password_verify ($code, $codecrypted)){
                $this->securityError ();
                return;
            }
            $key = openssl_pkey_get_private ($privatekeycryp, $email);
            if ($key === false){
                ?>
                <strong><p>La dirección de correo indicada tiene un problema de seguridad.</p>
                <p>Contacte con la administración del sitio.</p></strong>
                <?php
                return;
            }
            openssl_pkey_export($key, $priv);
            $_SESSION['privkey'] = encrypt ($priv, $this->key);
            $_SESSION['surveyid'] = $surveyid;
            $_SESSION['participantid'] = $participantid;
            $this->showSurvey ($db, $surveyid, $participantid);
        }
        catch (Exception $e){
            echo ("<p><strong>Error recuperando los datos para la participación</strong></p>");
            logMessage (LOGGER_ERROR, "Error {$e} when getting data for response.");
            clearSessionVariables ();
            return;
        }
    }

    private function securityError (){
        ?>
        <p><strong>La dirección de correo o el código para participar no son 
            correctos</strong></p>
        <?php
    }

    private function showSurvey ($db, $surveyid){
        $query = $db->prepare ("SELECT surveyname FROM {Surveys} where surveyid = :sid");
        $query->bindParam (":sid", $surveyid, PDO::PARAM_INT);
        $query->execute ();
        if ($query->rowCount () == 0){
            ?>
            <p><strong>No se encuentra la consulta seleccionada</strong></p>
            <?
            return;
        }
        $survey = $query->fetch ();
        ?>
        <h2>Participando en la consulta <em><?= $survey['surveyname'] ?></em></h2>
        <form name="participate" id="participate" action="participate" method="POST">
        <?php
        $query->closeCursor ();
        echo (setTokenHTML ());
        $questions = $db->prepare ("SELECT * FROM {Questions} WHERE surveyid = :sid");
        $questions->bindParam (":sid", $surveyid, PDO::PARAM_INT);
        $questions->execute ();
        if ($questions->rowCount () == 0){ //This should not happen
            echo ("<p><strong>No hay preguntas para esta consulta.</strong></p>");
            logMessage (LOGGER_ERROR, "No questions for survey {$surveyid}");
            return;
        }
        echo ("<input type='hidden' name='totalquestions' id='totalquestions' value='" . $questions->rowCount () . "'>");
        while ($question = $questions->fetch ()){
            $questionid = $question['questionid'];
        ?>

        <div class="question">
            <h3>Pregunta <?= $question['questionid'] ?></h3>
            <div><?= $question['questiondesc'] ?><div>
            <?php
            $multiple = $question['multiple'];
            $optional = $question['optional'];
            $options = $db->prepare ("SELECT * FROM {Options} WHERE surveyid = :sid " .
                "AND questionid = :qid");
            $options->bindParam (":sid", $surveyid, PDO::PARAM_INT);
            $options->bindParam (":qid", $questionid, PDO::PARAM_INT);
            $options->execute ();
            if ($options->rowCount () == 0){ //Should not happen
                echo ("<p><strong>No hay opciones para esta pregunta.</strong></p>");
                logMessage (LOGGER_ERROR, "No options for survey {$surveyid}-{$questionid}");
                $options->closeCursor ();
                continue;
            }
            if ($optional != 1){
                echo ("<p style='color: red;'><strong>Obligatoria</strong></p>");
            }
            ?>
            <div class="option">
                <input type="hidden" name="multiple-<?= $questionid; ?>" value="<?= $multiple; ?>">
                <input type="hidden" name="optional-<?= $questionid; ?>" value="<?= $optional; ?>">
                <input type="hidden" name="topt-<?= $questionid ?>" id="topt-<?= $questionid ?>" value="<?= $options->rowCount (); ?>">
                <?php
                while ($option = $options->fetch ()){
                    $optionid = $option['optionid'];
                    $optiondesc = $option['optiondesc'];
                    if ($multiple){
                        $cname = "op-" . $questionid . "-" . $optionid;
                        echo ("<p><input type='checkbox' id='{$cname}' name='{$cname}' " .
                            "><label for='{$cname}'>{$optiondesc}</label></p>");
                    }
                    else {
                        $rname = "op-" . $questionid;
                        $rid = "op-" . $questionid . "-" . $optionid;
                        echo ("<p><input type='radio' id='{$rid}' name='{$rname}' " .
                            "value='{$optionid}'><label for='{$rid}'>{$optiondesc}</label></p>");
                    }
                }
                $options->closeCursor ();
                ?>
            </div>
        </div>
        <?php
        }
        ?>
        <p><input type="submit" class="button-3" name="<?= self::ACTION; ?>" 
            value="<?= self::ACTION ?>"></p>
        </form>
        <?php
    }

    private function insertResponse (){
        if (!checkToken ()){
            tokenError ();
            return;           
        }
        $surveyid = $_SESSION['surveyid'];
        $participantid = $_SESSION['participantid'];

        //This block should be remove in non alpha versions
        if ($participantid == self::TESTID){
            $privkey = false;
        }
        else {
            if (!isset ($_COOKIE[self::COOKIE_KEY])){
                echo ("<p><strong>Error recuperando las cookies para firmar las respuestas.</strong></p>");
                return;
            }
            try {
                $privkey = decrypt ($_SESSION['privkey'], base64_decode ($_COOKIE[self::COOKIE_KEY]));
            }
            catch (Exception $e){
                echo ("<p><strong>Error cryptográfico al firmar las respuestas.</strong></p>");
                logMessage (LOGGER_ERROR, "Signing response: {$e}");
                return;
            }
        }

        clearSessionVariables ();
        try {
            $responsearray = array (); 
            $db = dbConn ();
            $questions = $db->prepare ("SELECT questionid, multiple, optional " . 
                "FROM {Questions} WHERE surveyid = :sid ORDER BY questionid ASC");
            $questions->bindParam (":sid", $surveyid, PDO::PARAM_INT);
            $questions->execute ();
            if ($questions->rowCount () > 0){
                while ($question = $questions->fetch ()){
                    $questionid = $question['questionid'];
                    $optional = $question['optional'];
                    $multiple = $question['multiple'];
                    if ($multiple == 0){
                        if (isset ($_REQUEST["op-" . $questionid]))
                            $responsearray[$questionid] = $_REQUEST["op-" . $questionid];
                        else if ($optional == 1)
                            $responsearray[$questionid] = -1;
                        else{
                            echo ("<p><strong>Error. Hay preguntas obligatioras no respondidas.</strong></p>");
                            $questions->closeCursor ();
                            return;
                        }
                    }
                    else {
                        $toptions = $_REQUEST["topt-" . $questionid];
                        $responsearray[$questionid] = array();
                        for ($i = 1; $i <= $toptions; $i++){
                            if (isset ($_REQUEST["op-" . $questionid . "-" . $i]))
                                $responsearray[$questionid][$i] = 1;
                            if ($optional == 0 && empty ($responsearray[$questionid])){
                                echo ("<p><strong>Error. Hay preguntas obligatioras no respondidas.</strong></p>");
                                $questions->closeCursor ();
                                return;
                            }
                        }
                    }
                }
            }
            $questions->closeCursor ();
            $responsejson = json_encode ($responsearray);
            if ($responsejson === false){
                echo ("<p><strong>Error codificando respuestas.</strong></p>");
                $err = json_last_error_msg ();
                logMessage (LOGGER_ERROR, "Error {$err} in json_encode");
                return;
            }
            $responsesign = $this->signResponse ($db, $responsejson, $privkey, $participantid);
            $query = $db->prepare ("INSERT INTO {Responses} (surveyid, participantid, response, " .
                " responsesign) values (:sid, :pid, :res, :ress)");
            $query->bindParam (":sid", $surveyid, PDO::PARAM_INT);
            $query->bindParam (":pid", $participantid, PDO::PARAM_INT);
            $query->bindParam (":res", $responsejson, PDO::PARAM_STR);
            $query->bindParam (":ress", $responsesign, PDO::PARAM_STR);
            $query->execute ();
            ?>
            <p><strong>Respuestas guardadas.</strong></p>
            <p>Gracias por participar en la consulta.</p>
            <?php
        }   
        catch (Exception $e){
            echo ("<p><strong>Error guardando respuestas.</strong></p>");
            logMessage (LOGGER_ERROR, "{$e} inserting responses.");
            return;
        }
    }

    private function signResponse ($db, $response, #[\SensitiveParameter] $key, $participantid){
        if ($key === false){
            return "no sign";
        }
        $signkey = openssl_pkey_get_private ($key);
        if (!openssl_sign ($response, $sign, $signkey, getSignAlgo ())){
            throw new Exception("Error in signing " . openssl_error_string());
            return "";
        }
        $query = $db->prepare ("SELECT publickey FROM {Participants} WHERE participantid = :pid");
        $query->bindParam (":pid", $participantid);
        $query->execute ();
        if ($query->rowCount () == 0){
            throw new Exception("Can't find participant for signing: {$participantid}");
            return "";
        }
        $row = $query->fetch ();
        $pubkey = openssl_pkey_get_public ($row['publickey']);
        if ($pubkey === false){
            throw new Exception("Error getting participant keypair: {$participantid}");
            return "";
        }
        $query->closeCursor ();
        $res = openssl_verify ($response, $sign, $pubkey, getSignAlgo ());
        if ($res == 0){
            throw new Exception("Bad signature signing response");
            return "";
        }
        else if ($res == -1 || $res == false){
            throw new Exception("Error signing response " . openssl_error_string());
            return "";
        }
        return base64_encode ($sign);
    }
}

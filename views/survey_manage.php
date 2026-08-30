<?php
require_once 'ifaces/view.php';
require_once 'utils/user.php';

enum SurveyJavascript {
        case NoJavascript;
        case ListJavascript;
        case AddJavascript;
        case ModifJavascript;
    };

class SurveyManage extends View {
    private $surveyscount = 0;
    private const MANAGEACTION = 'manageaction';
    private const ADDACTION = 'addaction';
    private const MODIFYACTION = 'modifaction';
    private const DELACTION = 'delaction';

    function getMenuGroup (){
        return ML_MENU_GROUP_SURVEYS;
    }    

    private SurveyJavascript $javascriptype = SurveyJavascript::NoJavascript;

    function isMulticol (){
        startSession ();
        if (isUser ())
            return true;

        return false;
    }

    function leftMenu (){
        include 'include/userleftcolumn.php';
    }

    function show (){
        if (!isAdmin ()){
            showMain ();
            return;
        }
        if (isset($_REQUEST[self::MANAGEACTION])){
            $action = $_REQUEST[self::MANAGEACTION];
            unset ($_REQUEST[self::MANAGEACTION]);
            switch ($action) {
                case 'Añadir':
                    $this->showAddSurvey ();
                    return;
                    break;
                case 'Modificar':
                    $this->showModifySurvey ();
                    return;
                case 'Eliminar':
                    //The warning should be previous;
                    $this->deleteSurvey ();
                    break;
                default:
                    logMessage (LOGGER_ERROR, "Unkown surveys manage action {$action}");
                    break;
            }
        }
        else if (isset($_REQUEST[self::ADDACTION])){
            $action = $_REQUEST[self::ADDACTION];
            unset ($_REQUEST[self::ADDACTION]);
            switch ($action) {
                case 'Aceptar':
                    $this->addSurvey ();
                    break;
                default:
                    break;
            }
        }
        else if (isset($_REQUEST[self::MODIFYACTION])){
            $action = $_REQUEST[self::MODIFYACTION];
            unset ($_REQUEST[self::MODIFYACTION]);
            switch ($action) {
                case 'Aceptar':
                    $this->modifySurvey ();
                    break;
            }
        }
        ?>
        <div class="col-md-8">
        <h2>Gestión de consultas</h2>
        <form id="surveymanage" name="surveymanage" method="POST" 
            action="survey_manage" >
        <?php
        $this->listSurveys ();
        $this->showControls ();
        echo ('</form></div>');

    }

    private function listSurveys (){
        try {
            $dbconn = dbConn ();
            $query = $dbconn->prepare ("SELECT surveyid, surveyname" .
            ", DATE_FORMAT(enddate,'%d/%m/%Y %T') as dend" . 
            ", DATE_FORMAT(startdate,'%d/%m/%Y %T') as dstart FROM {Surveys}" .
            " WHERE startdate > NOW()" .
                " ORDER BY startdate DESC");
            $query->execute ();
            $this->surveyscount = $query->rowCount ();
            if ($this->surveyscount == 0){
                ?>
                    <p><strong>Actualmente no hay consultas para gestionar.</strong></p>
                <?php
                return;
            }
            ?>
            <p>Solo es posible gestionar las consultas que aún no hayan comenzado.</p>
            <link href="css/tablecard.css" rel="stylesheet" />
            <link href="css/button3.css" rel="stylesheet" />
<div class="card-table-container">
            <table class="card-like-table" id="surveystable">
                <thead><tr>
                    <td>Consulta</td><td>Fecha inicio</td><td>Fecha fin</td><td>Seleccionar</td>
                </tr></thead>
                <tbody>
                <?php
                while ($row = $query->fetch ()){
                    $id = $row['surveyid'];
                    ?>
                    <tr id="<?= $id; ?>">
                        <td><span class="username" id="sv-<?= $id; ?>"><?= $row['surveyname']?></span></td>
                        <td><?= $row['dstart'] ?></td>
                        <td><?= $row['dend'] ?></td>
                        <td><input type="radio" name="surveyid" value="<?= $id; ?>" id="rb-<?= $id; ?>"></td>
                    </tr>
                    <?php
                }
                $query->closeCursor ();
                ?>
                </tbody>
            </table>
</div>
<script type="text/javascript">
                const miTabla = document.getElementById("surveystable");

                miTabla.addEventListener("click", function(evento) {
                    // Encuentra la fila (tr) más cercana al elemento que recibió el clic
                    const fila = evento.target.closest("tr");
  
                    if (!fila) {
                        return;
                    }
                    var userid = 'rb-' + fila.id;
                    document.getElementById (userid).checked = true;    
                });
            </script>
            <?php
        }
        catch (Exception $e){
            ?>
                <p>Error obteniendo la lista de consultas. Contacte con soporte.</p>
            <?php
            logMessage (LOGGER_ERROR, "DB error {$e} getting surveys list");
        }
    }

    private function showControls (){
       ?>
       <script type="text/javascript">
        function validate_modify (){
            var selectedradio = document.querySelector('input[name="surveyid"]:checked');
            if (!selectedradio){
                alert ('No has seleccionado ninguna para modificar');
                return false;
            }
            return true;
        }
        function confirm_delete (){
            var selectedradio = document.querySelector('input[name="surveyid"]:checked');
            if (!selectedradio){
                alert ('No has seleccionado ninguna para eliminar');
                return false;
            }
            var element =  'sv-' + selectedradio.value;
            var surveyname = document.getElementById (element).innerText;
            return confirm ("¿Seguro que quieres eliminar la consulta " + surveyname + 
            "?\nEsto no se puede deshacer");
        }
        </script>
       <p>
       <input type="submit" class="button-3" name="<?= self::MANAGEACTION ?>" id="add" value="Añadir">
       <?php
       if ($this->surveyscount > 0){
        ?>
       <input type="submit" class="button-3" name="<?= self::MANAGEACTION ?>" onclick="return validate_modify ();" id="modify" value="Modificar">
       <input type="submit" class="button-3" name="<?= self::MANAGEACTION ?>" onclick="return confirm_delete();" id="delete" value="Eliminar">
       <?php
       }
       ?>
        </p>
        <?php
       
    }

    function loadStyles (){
        ?>
        <link href="css/tablecard.css" rel="stylesheet" />
        <link href="css/button3.css" rel="stylesheet" />
        <link href="css/questions.css" rel="stylesheet" />
        <?php
    }

    
    private function showAddSurvey (){
        $this->javascriptype = SurveyJavascript::AddJavascript;
    ?>
    <div class="col-md-8">
    <h2>Añadir consulta</h2>
        <form id="addsurvey" name="addsurvey" method="POST" action="survey_manage" 
onload='document.getElementById("survey").focus();'>
        <p><label for="survey">Consulta:</label>
            <input type="text" id="survey" name="survey" tabindex="-1"></p>
        <p><label for="startdate">Inicio:</label>
            <input type="datetime-local" id="startdate" name="startdate"></p>
        <p><label for="enddate">Fin:</label>
            <input type="datetime-local" id="enddate" name="enddate"></p>
        <?php
         $this->addQuestionsTable ();
         ?>
        <p><input class="button-3" type="submit" onclick="return validate_add ();" name="<?= self::ADDACTION ?>" id="ok" value="Aceptar">
        <input class="button-3" type="submit" name="<?= self::ADDACTION ?>" id="cancel" value="Cancelar">
        </p>
                </form>
                </div>
        <?php
    }

    public function addJavascript (){
        switch ($this->javascriptype) {
             case SurveyJavascript::NoJavascript:
                break;
             case SurveyJavascript::AddJavascript:
                $this->writeAddJavascript ();
                break;
        }
    }

    private function writeAddJavascript (){
        $this->javascriptype = SurveyJavascript::NoJavascript;
        ?>
        <script type="text/javascript">
            var questions = 0;

        function validate_question (id){
            /*var e = document.getElementById ("name-q-" + id);
            if (e == null)
                return true;
            if (e.value == ""){
                alert ("El nombre de la pregunta " + id + 
                " no puede estar vacío.");
                e.focus ({preventScroll: false, focusVisible: true});
                return false;
            }*/
            var e = document.getElementById ("desc-q-" + id);
            if (e.value == ""){
                alert ("La descripcióm de la pregunta " + id + 
                " no puede estar vacío.");
                e.focus ({preventScroll: false, focusVisible: true});
                return false;
            }

            var opt = 1;
            e = document.getElementById ("opt-" + id + "-" + opt);
            while (e != null){
                if (e.value == ""){
                    alert ("La opción " + opt + " de la pregunta " + id +
                        " no puede estar vacía."
                    );
                    e.focus ({preventScroll: false, focusVisible: true});
                    return false;
                }
                opt++;
                e = document.getElementById ("opt-" + id + "-" + opt);
            }
            return true;
        }
        function validate_add (){
            const survey = document.getElementById("survey");
            const start = document.getElementById("startdate");
            const end = document.getElementById("enddate"); 
            const startdate = new Date (start.value);
            const enddate = new Date (end.value);
            const today = new Date ();
            //const todaystring = today.toISOString ().substring (0,19);
            
            
            if (survey.value == ""){
                alert ("El nombre de la consulta no puede estar vacío");
                survey.focus ({preventScroll: false, focusVisible: true});
                return false;
            }

            if (start.value == ""){
                alert ("Tienes que indicar una fecha de inicio.");
                start.focus ({preventScroll: false, focusVisible: true});
                return false;
            }
            if (end.value == ""){
                alert ("Tienes que indicar una fecha de finalización.");
                end.focus ({preventScroll: false, focusVisible: true});
                return false;
            }

                        
            if (startdate <= today){
                alert ('La fecha de inicio debe ser posterior a ahora.')
                return false;
            }
            if (enddate <= startdate){
                alert ("La fecha de fin debe ser posterior a la de inicio.")
                return false;
            }
            var question = 1;
            var q = document.getElementById ("q-" + question);
            while (q != null){
                if (!validate_question (question))
                    return false;
                question++;
                q = document.getElementById ("q-" + question);
            }
            return true;
        }
        </script>
        <?php
        $this->insertQuestionsCode ();
        $this->insertOptionsCode();
    }

    private function insertQuestionsCode (){
        ?>
        <script type="text/javascript">

        function retagquestion (oldid, newid){
            //var names=["name-q-", "desc-q-", "opt-q-", "mul-q-"]
            var names=["desc-q-", "opt-q-", "mul-q-"]
            var e = document.getElementById ("q-" + oldid);
            if (e == null)
                return false;

            e.id = "q-" + newid;
            e = document.getElementById ("qn-" + oldid);
            e.id = "qn-" + newid;
            e.innerText = "Pregunta " + newid;
            for (let j = 0; j < names.length; j++){
                e = document.getElementById ("label-" + names[j] + oldid);
                e.id = "label-" + names[j] + newid;
                e.htmlFor = names[j] + newid;
                e = document.getElementById (names[j] + oldid);
                e.id = names[j] + newid;
                e.name = names[j] + newid;
            }
            e = document.getElementById ("add-q-" + oldid);
            e.id = "add-q-" + newid;
            e.onclick = addquestion.bind (this, newid);
            e = document.getElementById ("del-q-" + oldid);
            if (e != null){
                e.id = "del-q-" + newid;
                e.onclick = delquestion.bind (this, newid);
            }

            //Options
            e = document.getElementById ("p-opt-" + oldid + "-1");
            e.id = "p-opt-" + newid + "-1";
            e = document.getElementById ("opt-" + oldid + "-1");
            e.id = "opt-" + newid + "-1";
            e.name = "opt-" + newid + "-1";

            e = document.getElementById ("p-opt-" + oldid + "-2");
            e.id = "p-opt-" + newid + "-2";
            e = document.getElementById ("opt-" + oldid + "-2");
            e.id = "opt-" + newid + "-2";
            e.name = "opt-" + newid + "-2";
            e = document.getElementById ("add-opt-" + oldid + "-2");
            e.id = "add-opt-" + newid + "-2";
            e.onclick = addoption.bind (this, newid, 2);
            var optid = 3;
            e = document.getElementById ("p-opt-" + oldid + "-" + optid);
            while (e != null){
                e.id = "p-opt-" + newid + "-" + optid;
                e = document.getElementById ("opt-" + oldid + "-" + optid);
                e.id = "opt-" + newid + "-" + optid;
                e.name = "opt-" + newid + "-" + optid;
                e = document.getElementById ("add-opt-" + oldid + "-" + optid);
                e.id = "add-opt-" + newid + "-" + optid;
                e.onclick = addoption.bind (this, newid, optid);
                e = document.getElementById ("del-opt-" + oldid + "-" + optid);
                if (e != null)
                    e.onclick = deloption.bind (this, newid, optid);
                optid++;
                e = document.getElementById ("p-opt-" + oldid + "-" + optid);
            }
            return true;
        }

        function addquestion(questionid) {
            if (!validate_question (questionid)){
                alert ("Debes completar los datos de la pregunta antes de añadir otra.")
                return;
            }
            var nextquestionid = questionid + 1;
            var morequestionsid = nextquestionid;
            var question = document.getElementById ("q-" + morequestionsid);
            while (question != null){
                morequestionsid++;
                question = document.getElementById ("q-" + morequestionsid);
            }

            if (nextquestionid < morequestionsid){
                for (let i = morequestionsid; i > nextquestionid; i--){
                    retagquestion (i-1, i);
                }
            }
            let newquestionhtml = `
            <div class="question" id="q-{qid}">
            <h3 id="qn-{qid}">Pregunta {qid}</h3>
            <!--<p><label id="label-name-q-{qid}" for="name-q-{qid}">Título:</label>
                <input type="text" id="name-q-{qid}" name="name-q-{qid}"></p>-->
            <p><label id="label-desc-q-{qid}" for="desc-q-{qid}">Descripción:</label>
                <textarea class="form-control" name="desc-q-{qid}" id="desc-q-{qid}"></textarea></p>
            <p><label id="label-opt-q-{qid}" for="opt-q-{qid}">Opcional:</label>
                <input type="checkbox" name="opt-q-{qid}" id="opt-q-{qid}">
                <label id="label-mul-q-{qid}" for="mul-q-{qid}">Multiple:</label><input type="checkbox" 
                name="mul-q-{qid}" id="mul-q-{qid}"></p>
            <p><input id="add-q-{qid}" type="button" class="button-3" 
                                onclick="addquestion ({qid});" value="Añadir pregunta">
                <input id="del-q-{qid}" type="button" class="button-3" 
                                onclick="delquestion ({qid});" value="Eliminar pregunta"></p>
                                
            <div class="option">
                <p><strong>Opciones:</strong></p>
                <p id="p-opt-{qid}-1"><input type="text" name="opt-{qid}-1" id="opt-{qid}-1"></p>
                <p id="p-opt-{qid}-2"><input type="text" name="opt-{qid}-2" id="opt-{qid}-2">
                    <button type="button" id="add-opt-{qid}-2" class="button-3" onclick="addoption ({qid},2);">
                        <img src="img/add.svg" />
                    </button>
                </p>
             </div>
        </div>`;
            /*newquestion = document.createElement ("div");
            newquestion.id = "q-" + nextquestionid;
            newquestion.class = "question";*/
            newquestion = newquestionhtml.replace (/{qid}/g, nextquestionid);
            let position = document.getElementById ("q-" + questionid);
            position.insertAdjacentHTML("afterend", newquestion);
        }


        function delquestion (id){
            var e = document.getElementById ("q-" + id);
            e.remove ();
            id++;
            while (retagquestion (id, id-1)){
                id++;
            }
        }
        </script>
        <?php
    }

    private function insertOptionsCode (){
        ?>
        <script type="text/javascript">
        function deloption (questionid, optid){
            var option = document.getElementById ("p-opt-" + questionid +
                "-" + optid
            );
            option.remove ();
            while (retagoption(questionid,optid+1, optid))
                optid++;

        }
        function retagoption (questionid, oldid, newid){
            var lastopt = document.getElementById ("p-opt-" + questionid + "-" +
                oldid
            );
            if (lastopt == null){
                return false;
            }
            lastopt.id = "p-opt-" + questionid + "-" + newid;
            var lastopt = document.getElementById ("opt-" + questionid + "-" +
                oldid
            );
            lastopt.id = "opt-" + questionid + "-" + newid;
            lastopt.name = "opt-" + questionid + "-" + newid;
            lastopt = document.getElementById ("add-opt-" + questionid + "-" +
                oldid
            );
            lastopt.id = "add-opt-" + questionid + "-" + newid;
            lastopt.onclick = addoption.bind (this, questionid, newid);

            lastopt = document.getElementById ("del-opt-" + questionid + "-" +
                oldid
            );
            lastopt.id = "del-opt-" + questionid + "-" + newid;
            lastopt.onclick = deloption.bind (this, questionid, newid);
            return true;
        }
        function addoption (questionid, optionid){
            var curropt = document.getElementById ("opt-" + questionid + "-" +
                optionid
            );
            if (!validate_question (questionid)){
                alert ("Debes completar todos los datos de la pregunta antes de añadir otra opción");
                return false;
            }
            var lastid = optionid + 1;
            var nextoptid = lastid;
            var lastopt = document.getElementById ("p-opt-" + questionid + "-" +
                lastid
            );
            while (lastopt != null){
                lastid++;
                lastopt = document.getElementById ("p-opt-" + questionid + "-" +
                    lastid
                );
            }
            for (let i = lastid; i > nextoptid; i--){
                retagoption (questionid, i-1, i);
            }
            var optHTML = `<p id="p-opt-{qid}-{oid}">
                <input type="text" name="opt-{qid}-{oid}" id="opt-{qid}-{oid}">
                    <button type="button" id="add-opt-{qid}-{oid}" class="button-3" 
                        onclick="addoption ({qid},{oid});">
                        <img src="img/add.svg" />
                    </button>
                    <button type="button" id="del-opt-{qid}-{oid}" class="button-3" 
                        onclick="deloption ({qid},{oid});">
                        <img src="img/del.svg" />
                    </button>
                </p>`;
            optHTML1 = optHTML.replace (/{qid}/g, questionid);
            optHTML2 = optHTML1.replace (/{oid}/g, nextoptid);
            let position = document.getElementById ("p-opt-" + questionid + 
                "-" + optionid
            );
            position.insertAdjacentHTML("afterend", optHTML2);    
        }
        </script>
        <?php
    }

    private function addQuestionsTable (){
        ?>

        <div class="question" id="q-1">
            <h3 id="qn-1">Pregunta 1</h3>
            <!--<p><label id="label-name-q-1" for="name-q-1">Título:</label>
                <input type="text" id="name-q-1" name="name-q-1"></p>-->
            <p><label id="label-desc-q-1" for="desc-q-1">Descripción:</label>
                <textarea class="form-control" name="desc-q-1" id="desc-q-1"></textarea></p>
            <p><label id="label-opt-q-1" for="opt-q-1">Opcional:</label>
                <input type="checkbox" name="opt-q-1" id="opt-q-1">
                <label id="label-mul-q-1" for="mul-q-1">Multiple:</label><input type="checkbox" name="mul-q-1"
                id="mul-q-1"></p>
            <p><input id="add-q-1" type="button" class="button-3" 
                                onclick="addquestion(1)" value="Añadir pregunta"></p>
                                
            <div class="option">
                <p><strong>Opciones:</strong></p>
                <p id="p-opt-1-1"><input type="text" name="opt-1-1" id="opt-1-1"></p>
                <p id="p-opt-1-2"><input type="text" name="opt-1-2" id="opt-1-2">
                    <!--<input id="add-opt-1-2" type="button" class="button-3" 
                                    onclick="addoption (1,2);" value="Añadir opción">-->
                    <button type="button" id="add-opt-1-2" class="button-3" onclick="addoption (1,2);">
                        <img src="img/add.svg" alt="Añadir">
                    </button>
                </p>
             </div>
        </div>
        <?php


    
    }

    private function addSurvey (){
        $surveyname = $_REQUEST['survey'];
        $startstring = $_REQUEST['startdate'];
        $endstring = $_REQUEST['enddate'];
        $nquestion = 1;
        $noption = 1;
        $questions = array();
        while (isset($_REQUEST['desc-q-' . $nquestion])){
            $questions[$nquestion] = array();
            //$questions[$nquestion]['name'] = $_REQUEST['name-q-' . $nquestion];
            $questions[$nquestion]['desc'] = $_REQUEST['desc-q-' . $nquestion];
            $questions[$nquestion]['optional'] = isset(
                $_REQUEST['opt-q-' . $nquestion]);
            $questions[$nquestion]['multiple'] = isset(
                $_REQUEST['mul-q-' . $nquestion]);
            $noption = 1;
            $questions[$nquestion]['options'] = array();
            while (isset($_REQUEST['opt-' . $nquestion . '-' .$noption])){
                $questions[$nquestion]['options'][$noption] = 
                    $_REQUEST['opt-' . $nquestion . '-' .$noption];
                $noption++;
            }
            $nquestion++;
        }
        
        try {
            $dbconn = dbConn ();
            $dbconn->beginTransaction ();
            try {
                $query = $dbconn->prepare ("INSERT into {Surveys} " . 
                    "(surveyname, startdate, enddate) values (:name, :start, :end)");
                $query->bindParam (":name", $surveyname, PDO::PARAM_STR);
                $query->bindParam (":start", $startstring, PDO::PARAM_STR);
                $query->bindParam (":end", $endstring, PDO::PARAM_STR);
                $query->execute ();
                $sid = $dbconn->lastInsertId ();
                $this->insertQuestions ($dbconn, $sid, $questions);
                $dbconn->commit ();
            }
            catch (Exception $e){
                $dbconn->rollBack ();
                throw $e;
                
            }
        }
        catch (Exception $e){
            echo ("<strong>Error creando la consulta.</strong>");
            logMessage (LOGGER_ERROR, "Error {$e} inserting survey.");
        }

    }

    private function insertQuestions ($dbconn, $sid, $questions){
        foreach ($questions as $qid => $question) {
            $query = $dbconn->prepare ("INSERT into {Questions} " .
                "(surveyid, questionid, questiondesc, optional, multiple) " .
                "values (:sid, :qid, :desc, :opt, :mul)");
            $query->bindParam (":sid", $sid, PDO::PARAM_INT);
            $query->bindParam (":qid", $qid, PDO::PARAM_INT);
            $query->bindParam (":desc", $question['desc'], PDO::PARAM_STR);
            $query->bindParam (":opt", $question['optional'], PDO::PARAM_BOOL);
            $query->bindParam (":mul", $question['multiple'], PDO::PARAM_BOOL);
            $query->execute ();

            $query = $dbconn->prepare ("INSERT into {Options} " .
                "(surveyid, questionid, optionid, optiondesc) " .
                "values (:sid, :qid, :oid, :desc)");
            $oid = 0;
            $option = '';
            $query->bindParam (":sid", $sid, PDO::PARAM_INT);
            $query->bindParam (":qid", $qid, PDO::PARAM_INT);
            $query->bindParam (":oid", $oid, PDO::PARAM_INT);
            $query->bindParam (":desc", $option, PDO::PARAM_STR);
            foreach ($question['options'] as $oid => $option){
                $query->execute ();
            }
        }
    }

    private function deleteSurvey (){
        if (!isset ($_REQUEST['surveyid']))
            return;
        try {
            $dbconn = dbConn ();
            $dbconn->beginTransaction ();
            try {
                $query = $dbconn->prepare ("Delete from {Surveys} where " . 
                    "surveyid = :sid");
                $query->bindParam (":sid", $_REQUEST['surveyid'], PDO::PARAM_INT);
                $query->execute ();
                $dbconn->commit ();
            }
            catch (Exception $e){
                $dbconn->rollBack ();
                throw $e;
                
            }
        }
        catch (Exception $e){
            echo ('<strong>Error eliminando la consulta.</strong>');
            logMessage (LOGGER_ERROR, "Error {$e} deleting survey.");
        }
    }

    private function showModifySurvey (){
        if (!isset ($_REQUEST['surveyid']))
            return;
        $sid = $_REQUEST['surveyid'];
        $_SESSION['surveyid'] = $sid;
        $this->javascriptype = SurveyJavascript::AddJavascript;
        $dbconn = dbConn ();
        $surveys = $dbconn->prepare ("SELECT * from {Surveys} where surveyid = :sid");
        $surveys->bindParam (":sid", $sid, PDO::PARAM_INT);
        $surveys->execute ();
        if ($surveys->rowCount() == 0)
            return;
        $survey = $surveys->fetch ();
    ?>
    <div class="col-md-8">
    <h2>Modificar consulta</h2>
        <form id="modsurvey" name="modsurvey" method="POST" action="survey_manage" 
onload='document.getElementById("survey").focus();'>
        <p><label for="survey">Consulta:</label>
            <input type="text" id="survey" name="survey" tabindex="-1"
            value="<?= $survey['surveyname'] ?>"></p>
        <p><label for="startdate">Inicio:</label>
            <input type="datetime-local" id="startdate" name="startdate"
            value="<?= $survey['startdate'] ?>"></p>
        <p><label for="enddate">Fin:</label>
            <input type="datetime-local" id="enddate" name="enddate"
            value="<?= $survey['enddate'] ?>"></p>
        <?php
         $this->getQuestionsTable ($dbconn, $sid);
         $surveys->closeCursor ();
         ?>
        <p><input class="button-3" type="submit" onclick="return validate_add ();" 
            name="<?= self::MODIFYACTION ?>" id="ok" value="Aceptar">
        <input class="button-3" type="submit" name="<?= self::MODIFYACTION ?>" 
            id="cancel" value="Cancelar">
        </p>
                </form>
                </div>
        <?php
    }

    private function getQuestionsTable ($dbconn, $sid){
        $questions = $dbconn->prepare ("Select * from {Questions} where surveyid = :sid");
        $questions->bindParam (":sid", $sid);
        $questions->execute ();
        while ($question = $questions->fetch ()){
            $qid = $question['questionid']; 
            $mul = $question['multiple'] ? 'checked':'';
            $opt = $question['optional'] ? 'checked':'';
            ?>
            <div class="question" id="q-<?= $qid; ?>">
            <h3 id="qn-<?= $qid; ?>">Pregunta <?= $qid; ?></h3>
            <!--<p><label id="label-name-q-<?= $qid; ?>" for="name-q-<?= $qid; ?>">Título:</label>
                <input type="text" id="name-q-<?= $qid; ?>" name="name-q-<?= $qid; ?>"
                value=""></p>-->
            <p><label id="label-desc-q-<?= $qid; ?>" for="desc-q-<?= $qid; ?>">Descripción:</label>
                <textarea class="form-control" name="desc-q-<?= $qid; ?>" id="desc-q-<?= $qid; ?>"><?= $question['questiondesc']; ?></textarea></p>
            <p><label id="label-opt-q-<?= $qid; ?>" for="opt-q-<?= $qid; ?>">Opcional:</label>
                <input type="checkbox" name="opt-q-<?= $qid; ?>" id="opt-q-<?= $qid; ?>" 
                <?= $opt; ?>>
                <label id="label-mul-q-<?= $qid; ?>" for="mul-q-<?= $qid; ?>">Multiple:</label>
                <input type="checkbox" name="mul-q-<?= $qid; ?>"
                id="mul-q-<?= $qid; ?>" <?= $mul; ?>></p>
            <p><input id="add-q-<?= $qid; ?>" type="button" class="button-3" 
                                onclick="addquestion(<?= $qid;?>)" value="Añadir pregunta"></p>
            <div class="option">
                <p><strong>Opciones:</strong></p>
            <?php
            $this->getOptionsTable ($dbconn, $sid, $qid);
            ?>
            </div>
        </div>
            <?php
        }
        $questions->closeCursor ();

    }
    private function getOptionsTable ($dbconn, $sid, $qid){
        $options = $dbconn->prepare ("SELECT * from {Options} " .
            "where surveyid = :sid and questionid = :qid");
        $options->bindParam (":sid", $sid);
        $options->bindParam (":qid", $qid);
        $options->execute ();
        while ($option = $options->fetch ()){
            $oid = $option['optionid'];
            if ($oid == 1){
    ?>
    
                <p id="p-opt-<?= $qid; ?>-1"><input type="text" 
                name="opt-<?= $qid;?>-1" id="opt-<?= $qid;?>-1"
                value="<?= $option['optiondesc']; ?>"></p>
    <?php
            }
            else if ($oid == 2){?>
                <p id="p-opt-<?= $qid;?>-2"><input type="text" name="opt-<?= $qid;?>-2"
                 id="opt-<?= $qid;?>-2" value="<?= $option['optiondesc']; ?>">
                    
                    <button type="button" id="add-opt-<?= $qid;?>-2" class="button-3" 
                    onclick="addoption (<?= $qid;?>,2);">
                        <img src="img/add.svg" alt="Añadir">
                    </button>
                </p>
    <?php
            }
            else {
                $qoid = $qid . "-" . $oid;
            ?>
                <p id="p-opt-<?= $qoid; ?>">
                <input type="text" name="opt-<?= $qoid; ?>" id="opt-<?= $qoid; ?>"
                    value="<?= $option['optiondesc']; ?>">
                    <button type="button" id="add-opt-<?= $qoid; ?>" class="button-3" 
                        onclick="addoption (<?= $qid; ?>,<?= $oid; ?>);">
                        <img src="img/add.svg" />
                    </button>
                    <button type="button" id="del-opt-<?= $qoid; ?>" class="button-3" 
                        onclick="deloption (<?= $qid; ?>,<?= $oid; ?>);">
                        <img src="img/del.svg" />
                    </button>
                </p>
            <?php
            }
        }
        $options->closeCursor ();
    }

    private function modifySurvey(){
        if (!isset ($_SESSION['surveyid']))
            return;
        $sid = $_SESSION['surveyid'];
        unset ($_SESSION['surveyid']);        
        $surveyname = $_REQUEST['survey'];
        $startstring = $_REQUEST['startdate'];
        $endstring = $_REQUEST['enddate'];
        $nquestion = 1;
        $noption = 1;
        $questions = array();
        while (isset($_REQUEST['desc-q-' . $nquestion])){
            $questions[$nquestion] = array();
            //$questions[$nquestion]['name'] = $_REQUEST['name-q-' . $nquestion];
            $questions[$nquestion]['desc'] = $_REQUEST['desc-q-' . $nquestion];
            $questions[$nquestion]['optional'] = isset(
                $_REQUEST['opt-q-' . $nquestion]);
            $questions[$nquestion]['multiple'] = isset(
                $_REQUEST['mul-q-' . $nquestion]);
            $noption = 1;
            $questions[$nquestion]['options'] = array();
            while (isset($_REQUEST['opt-' . $nquestion . '-' .$noption])){
                $questions[$nquestion]['options'][$noption] = 
                    $_REQUEST['opt-' . $nquestion . '-' .$noption];
                $noption++;
            }
            $nquestion++;
        }
        try {
            $dbconn = dbConn ();
            $dbconn->beginTransaction ();
            try {
                $query = $dbconn->prepare ("UPDATE {Surveys} " . 
                    " set surveyname = :name, startdate = :start, " . 
                    "enddate = :end where surveyid = :sid");
                $query->bindParam (":name", $surveyname, PDO::PARAM_STR);
                $query->bindParam (":start", $startstring, PDO::PARAM_STR);
                $query->bindParam (":end", $endstring, PDO::PARAM_STR);
                $query->bindParam (":sid", $sid, PDO::PARAM_INT);
                $query->execute ();
                $query = $dbconn->prepare ("DELETE from {Questions} ".
                    " where surveyid = :sid");
                $query->bindParam (":sid", $sid, PDO::PARAM_INT);
                $query->execute ();
                $this->insertQuestions ($dbconn, $sid, $questions);
                $dbconn->commit ();
            }
            catch (Exception $e){
                $dbconn->rollBack ();
                throw $e;
            }                
        }
        catch (Exception $e){
            echo ("<strong>Error al modificar la consulta.</strong>");
            logMessage (LOGGER_ERROR, "Error {$e} when modifying survey");
        }
    }
}

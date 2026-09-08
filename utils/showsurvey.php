<?php
require_once 'utils/dbutils.php';
require_once 'include/fileparams.php';


function showTheSurvey ($db, $surveyid, $readonly = false){
    $setdisabled = "";
    if ($readonly){
        $setdisabled = "disabled";
    }
    $query = $db->prepare ("SELECT surveyname, surveydesc, surveyfile FROM {Surveys} where surveyid = :sid");
    $query->bindParam (":sid", $surveyid, PDO::PARAM_INT);
    $query->execute ();
    if ($query->rowCount () == 0){
        ?>
        <p><strong>No se encuentra la consulta seleccionada</strong></p>
        <?
        return;
    }
    $survey = $query->fetch ();
    if ($readonly){
        ?>
        <h2>Solicitando enlace para la consulta <em><?= $survey['surveyname'];?></em></h2>
        <p><em>Al final de la muestra de la consulta se encuentra la solicitud del enlace
            para participar.</em></p>
        <?php
    }
    else {
        echo ("<h2>Participando en la consulta <em>{$survey['surveyname']}</em></h2>");
    }
    ?>
    <div><?= $survey["surveydesc"]; ?></div>
    <?php
    if (!empty ($survey["surveyfile"])){
        $filelink = FileParams::FILE_DIR . $surveyid . "/" . $survey['surveyfile'];
    ?>
        <div><label for="filelink">Documentación adjunta:</label>
        <a href="<?= $filelink; ?>"><?= $survey["surveyfile"]; ?></a>
        </div>
    <?php
    }
    
    
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
        <div><?= $question['questiondesc'] ?></div>
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
                        "{$setdisabled}><label for='{$cname}'>{$optiondesc}</label></p>");
                }
                else {
                    $rname = "op-" . $questionid;
                    $rid = "op-" . $questionid . "-" . $optionid;
                    echo ("<p><input type='radio' id='{$rid}' name='{$rname}' " .
                        "value='{$optionid}' {$setdisabled}><label for='{$rid}'>{$optiondesc}</label></p>");
                }
            }
            $options->closeCursor ();
            ?>
        </div>
    </div>
    <?php
    }
    $questions->closeCursor ();
}
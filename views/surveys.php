<?php
require_once "ifaces/view.php";
require_once "utils/dbutils.php";

class Surveys extends View {
    public const SURVEY_RESPONSE = "response";
    private const SURVEY_QUERY = "query";
    private const RESPONSE_VALUES = [
        'code' => "Obtener código",
        'response' => "Responder"
    ];

    function getMenuGroup (){
        return ML_MENU_GROUP_SURVEYS;
    }

    public function loadStyles (){
        ?>
        <link href="css/tablecard.css" rel="stylesheet" />
        <link href="css/button3.css" rel="stylesheet" />
        <?php
    }
    public function show (){
        try {
            $db = dbConn ();
            echo ("<h2>Consultas activas.</h2>");
            $query = $db->prepare ("SELECT surveyid, surveyname" .
                ", DATE_FORMAT(enddate,'%d/%m/%Y %T') as dend" . 
                ", DATE_FORMAT(startdate,'%d/%m/%Y %T') as dstart ". 
                "FROM {Surveys} WHERE " .
                "startdate < NOW() and enddate > NOW() " .
                "ORDER BY startdate DESC");
            $query->execute ();
            if ($query->rowCount () == 0){
                echo ("<strong>No hay consultas activas para realizar.</strong>");
            }
            else {
                $this->addActiveSurveys ($query);
            }
            $query->closeCursor ();
            echo ("<h2>Consultas finalizadas.</h2>");
            $query = $db->prepare ("SELECT surveyid, surveyname" .
                ", DATE_FORMAT(enddate,'%d/%m/%Y %T') as dend" . 
                ", DATE_FORMAT(startdate,'%d/%m/%Y %T') as dstart ". 
                "FROM {Surveys} WHERE " .
                "enddate < NOW() " .
                "ORDER BY enddate DESC");
            $query->execute ();
            if ($query->rowCount () == 0){
                echo ("<p><strong>No hay consultas finalizadas para consultar.</strong></p>");
            }
            else {
                $this->addEndedSurveys ($query);
            }
            $query->closeCursor ();
        }
        catch (Exception $e){
            echo ("<strong>Error recuperando consultas.</strong>");
            logMessage (LOGGER_ERROR, "Error {$e} loading surveys.");
        }
    }

    private function addActiveSurveys ($surveys){
        ?>
        <form id="responsesurvey" name="responsesurvey" method="POST" action="">
        <script type="text/javascript">
        function validate_response (location){
            var selectedradio = document.querySelector('input[name="responseid"]:checked');
            if (!selectedradio){
                alert ('No has seleccionado ninguna para entrar');
                return false;
            }
            document.getElementById("responsesurvey").action = location;
            return true;
        }
        </script>
        <div class="card-table-container">
            <table class="card-like-table" id="surveystable">
                <thead><tr>
                    <td>Consulta</td><td>Fecha inicio</td><td>Fecha fin</td><td>Seleccionar</td>
                </tr></thead>
                <tbody>
                <?php
                while ($survey = $surveys->fetch ()){
                    $id = $survey['surveyid'];
                    ?>
                    <tr id="<?= "response_" . $id; ?>">
                        <td><span class="username" id="svr-<?= $id; ?>"><?= $survey['surveyname']?></span></td>
                        <td><?= $survey['dstart'] ?></td>
                        <td><?= $survey['dend'] ?></td>
                        <td><input type="radio" name="responseid" value="<?= $id; ?>" id="rrb-<?= $id; ?>"></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <p>
                <input type="submit" class="button-3" name="<?= self::SURVEY_RESPONSE; ?>" 
                    onclick="return validate_response ('get_code');" value="<?= self::RESPONSE_VALUES['code']; ?>">
                <input type="submit" class="button-3" name="<?= self::SURVEY_RESPONSE; ?>" 
                    onclick="return validate_response ('response_survey');" value="<?= self::RESPONSE_VALUES['response'] ?>">
            </p>
        </div>
        </form>
        <?php
    }

    private function addEndedSurveys ($surveys){
        ?>
        <script type="text/javascript">
        function validate_query (){
            var selectedradio = document.querySelector('input[name="queryid"]:checked');
            if (!selectedradio){
                alert ('No has seleccionado ninguna para ver resultados');
                return false;
            }
            return true;
        }
        </script>
        <div class="card-table-container">
            <table class="card-like-table" id="surveystable">
                <thead><tr>
                    <td>Consulta</td><td>Fecha inicio</td><td>Fecha fin</td><td>Seleccionar</td>
                </tr></thead>
                <tbody>
                <?php
                while ($survey = $surveys->fetch ()){
                    $id = $survey['surveyid'];
                    ?>
                    <tr id="<?= "query_" . $id; ?>">
                        <td><span class="username" id="svr-<?= $id; ?>"><?= $survey['surveyname']?></span></td>
                        <td><?= $survey['dstart'] ?></td>
                        <td><?= $survey['dend'] ?></td>
                        <td><input type="radio" name="queryid" value="<?= $id; ?>" id="qrb-<?= $id; ?>"></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <p>
                <input type="submit" class="button-3" name="<?= self::SURVEY_QUERY; ?>" 
                    onclick="return validate_query ();" value="Ver resultado">
            </p>
        </div>
        <?php
    }
}
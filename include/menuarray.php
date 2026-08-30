<?php
include_once 'ifaces/view.php';

const ML_MENU_LOCATION = "location";
const ML_MENU_ENTRY = "entry";
const ML_MENU_GROUP = "group";

const ML_MENU_GROUP_ADMIN = "admin";
const ML_MENU_GROUP_SURVEYS = "surveys";
$menuarray = [
    [ML_MENU_LOCATION => "index", ML_MENU_ENTRY => "Inicio", ML_MENU_GROUP => View::MENU_GROUP_NONE],
    [ML_MENU_LOCATION => "admin", ML_MENU_ENTRY => "Administración", ML_MENU_GROUP => ML_MENU_GROUP_ADMIN],
    [ML_MENU_LOCATION => "surveys", ML_MENU_ENTRY => "Consultas", ML_MENU_GROUP => ML_MENU_GROUP_SURVEYS],
];

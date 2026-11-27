<?php
class Services extends Controller
{
    public function __construct() {
        session_start();
        parent::__construct();
    }
    public function index()
    {
        $data['active'] = 'services';
        $data['title'] = 'Beneficios';
        $data['detail'] = 'Beneficios';
        $this->views->getView("services", $data);
    }
}

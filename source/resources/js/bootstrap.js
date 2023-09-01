/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';



window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import '../js/barra_2.0';
import '../js/jquery-3.5.1';
import '../../node_modules/popper.js/dist/umd/popper';
import '../../node_modules/bootstrap/dist/js/bootstrap';
import '../../node_modules/selectize/dist/js/selectize';



import '../js/datatables/jquery.dataTables';
import '../js/datatables/dataTables.bootstrap4';
import '../js/datatables-demo';

import '../js/change-contraste';

import '../js/ocorrencia_selectize';

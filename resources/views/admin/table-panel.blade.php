@extends('layouts.app')

@section('content')
    @include('admin.partials.navbar')
    <link rel="stylesheet" href="https://3s.unilab.edu.br/css/style_kamban.css">


    <div class="card mb-4">
        <div class="card-header pb-4 mb-4 font-italic">
            Tabela de Chamados Não Atendidos
            @include('admin.partials.form-filter-kanban')
            <button id="btn-expandir-tela" type="button"
                class="float-right btn ml-3 btn-warning btn-circle btn-lg collapsed"><i
                    class="fa fa-expand icone-maior"></i></button>
        </div>
        <div class="card-body" id="quadro-tabela">
            @include('admin.partials.table-panel-content')
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function(e) {


            var urlQuery = '?just_content=1';
            var selectSetores = $('#select-setores').selectize({
                maxItems: 50,
                onChange: function(selectedDivisions) {
                    var queryString = selectedDivisions.map(function(value) {
                        return 'division[]=' + value;
                    }).join('&');
                    urlQuery = '?just_content=1&'+queryString;
                }
            });


            $("#btn-expandir-tela").on('click', function(e) {
                fullScreenTable();
            });

            function fullScreenTable() {
                $("main").toggleClass("container");
                $("#cabecalho").toggleClass("escondido");
                $("#tabela-quadro").toggleClass("display-3");
            }

            function carregarDados(url2) {
                $.ajax({
                    type: 'GET',
                    url: url2,
                    success: function(response) {
                        $('#quadro-tabela').html(response);
                    }
                });
            }
            setInterval(function() {
                carregarDados(urlQuery);
            }, 3000);

        });
    </script>
@endsection

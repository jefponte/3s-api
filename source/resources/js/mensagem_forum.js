var url = new URL(window.location.href);
if (url.searchParams.get("page") === "ocorrencia" && url.searchParams.has("selecionar")) {

    var $chatbox = $('.chatbox');
    var $chatboxTitle = $('.chatbox__title');
    var $chatboxTitleClose = $('.chatbox__title__close');

    $chatboxTitle.on('click', function () {
        $chatbox.toggleClass('chatbox--tray');
    });
    $chatboxTitleClose.on('click', function (e) {
        e.stopPropagation();
        $chatbox.addClass('chatbox--closed');
    });
    $chatbox.on('transitionend', function () {
        if ($chatbox.hasClass('chatbox--closed')) $chatbox.remove();
    });

    $("#muda-tipo").on('change', function (e) {

        if ($("#muda-tipo").is(':checked')) {
            $("#campo-texto").addClass("escondido");
            $("#campo-anexo").removeClass("escondido");
            $("#campo_tipo").val(2);
        } else {

            $("#campo-texto").removeClass("escondido");
            $("#campo-anexo").addClass("escondido");
            $("#campo_tipo").val(1);

        }
    });






    function alocaMensagem(item, index) {
        console.log("Chamou");
        let data = new Date(item.data_envio);


        var mes = data.getMonth() + 1;
        var ano = data.getFullYear();
        var dia = data.getDate();
        var horas = data.getHours();
        var minutos = data.getMinutes();

        if (dia <= 9) {
            dia = "0" + dia;
        }
        if (mes <= 9) {
            mes = "0" + mes;
        }
        if (horas <= 9) {
            horas = "0" + horas;
        }
        if (minutos <= 9) {
            minutos = "0" + minutos;
        }

        var dataFormatada = dia + "/" + mes + "/" + ano;

        var div = '<div class="chatbox__body__message chatbox__body__message--left">';
        div += '<div class="chatbox_timing">';
        div += '<ul><li><a href="#"><i class="fa fa-calendar"></i> ' + dataFormatada + '</a></li>';
        div += '<li><a href="#"><i class="fa fa-clock-o"></i> ' + horas + ':' + minutos + '</a></li>';
        div += '</ul>';
        div += '</div>';
        div += '<div class="clearfix"></div>';
        div += '<div class="ul_section_full"><ul class="ul_msg">';

        div += '<li><strong>' + item.nome_usuario + '</strong></li>';
        if (item.tipo == 1) {
            div += '<li>' + item.mensagem + '</li>';
        } else {
            div += '<li>Anexo: <a href="./storage/uploads/' + item.mensagem + '" download>Download</a></li>';
        }

        div += '</ul>';
        div += '<div class="clearfix"></div>';
        div += '</div></div>';
        $("#corpo-chat").append(div);
        $("#corpo-chat").scrollTop($("#corpo-chat")[0].scrollHeight);


    }


    var idOcorrencia = $("#id-ocorrencia").text();
    var idUltima = $("#ultimo-id-post").text();
    var urlApiMensagem = "?api=api/mensagem_forum/";
    var url1 = urlApiMensagem + idOcorrencia + "/" + idUltima;

    var intervalId;

    function verifyMessages(url, alocaMensagem, interval) {
        $.ajax({
            url: url,
            success: function (data) {
                if (data.length > 0) {
                    data.forEach(
                        (element) => {
                            clearInterval(interval);
                            alocaMensagem(element);
                            let newUrl = urlApiMensagem + idOcorrencia + "/" + data[data.length - 1].id;
                            intervalId = setInterval(function() {
                                verifyMessages(newUrl, alocaMensagem, intervalId);
                            }, 10000);
                        });
                }
            }
        });
    }
    intervalId = setInterval(function() {
        verifyMessages(url1, alocaMensagem, intervalId);
    }, 10000);


    $("#corpo-chat").scrollTop($("#corpo-chat")[0].scrollHeight);
    $("#insert_form_mensagem_forum").on('submit', function (e) {
        e.preventDefault();


        var dados = new FormData(this);
        $('#botao-enviar-mensagem').attr('disabled', true);
        $('#botao-enviar-mensagem').text("Aguarde...");


        jQuery.ajax({
            type: "POST",
            url: "?ajax=mensagem_forum",
            data: dados,
            success: function (data) {

                console.log(data);
                if (data.split(":")[1] == 'sucesso') {

                    //window.location.href='?page=ocorrencia&selecionar='+data.split(":")[2];
                    $('#botao-enviar-mensagem').attr('disabled', false);
                    $('#botao-enviar-mensagem').text("Enviar");
                    $("#campo-texto").val("");
                    $("#corpo-chat").scrollTop($("#corpo-chat")[0].scrollHeight);

                }
                else {
                    alert("Falha ao enviar mensagem");
                }

            },
            cache: false,
            contentType: false,
            processData: false,
            xhr: function () { // Custom XMLHttpRequest
                var myXhr = $.ajaxSettings.xhr();
                if (myXhr.upload) { // Avalia se tem suporte a propriedade upload
                    myXhr.upload.addEventListener('progress', function () {
                        /* faz alguma coisa durante o progresso do upload */
                    }, false);
                }
                return myXhr;


            }
        });


    });




}
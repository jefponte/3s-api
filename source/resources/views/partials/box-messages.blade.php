<div class="container">
    <div class="row">
        <div class="chatbox chatbox22">
            <div class="chatbox__title">
                <h5 class="text-white">#<span id="id-ocorrencia">{{ $order->id }}</span></h5>
            </div>
            <div id="corpo-chat" class="chatbox__body">

                @foreach ($order->messages as $mensagemForum)
                    <div class="chatbox__body__message chatbox__body__message--left">

                        <div class="chatbox_timing">
                            <ul>
                                <li><a href="#"><i
                                            class="fa fa-calendar"></i>{{ date('d/m/Y', strtotime($mensagemForum->created_at)) }}</a>
                                </li>
                                <li><a href="#"><i class="fa fa-clock-o"></i>
                                        {{ date('H:i', strtotime($mensagemForum->created_at)) }}</a></a></li>
                            </ul>
                        </div>
                        <div class="clearfix"></div>
                        <div class="ul_section_full">
                            <ul class="ul_msg">
                                <li><strong> {{ $mensagemForum->firstName }}</strong></li>
                                @if ($mensagemForum->type == 'file')
                                    <li>Anexo: <a href="./storage/uploads/'{{ $mensagemForum->message }}">Clique
                                            aqui</a></li>
                                @else
                                    <li>{{ nl2br(htmlspecialchars($mensagemForum->message)) }}</li>
                                @endif


                            </ul>
                            <div class="clearfix"></div>

                        </div>

                    </div>
                @endforeach
                <span id="ultimo-id-post" class="escondido">{{ $order->messages->last() != null && $order->messages->last()->id }} </span>
            </div>
            <div class="panel-footer">
                @if ($order->canSendMessage)
                    <form id="insert_form_mensagem_forum" class="user" method="post">
                        <input type="hidden" name="enviar_mensagem_forum" value="1">
                        <input type="hidden" name="ocorrencia" value="' . $order->id . '">
                        <input type="hidden" id="campo_tipo" name="tipo" value="' . self::TIPO_TEXTO . '">

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" name="muda-tipo" id="muda-tipo">
                            <label class="custom-control-label" for="muda-tipo">Enviar Arquivo</label>
                        </div>
                        <div class="custom-file mb-3 escondido" id="campo-anexo">
                            <input type="file" class="custom-file-input" name="anexo" id="anexo"
                                accept="application/msword, application/vnd.ms-excel, application/vnd.ms-powerpoint, text/plain, application/pdf, image/*, application/zip,application/rar, .ovpn, .xlsx">
                            <label class="custom-file-label" for="anexo" data-browse="Anexar">Anexar um
                                Arquivo</label>
                        </div>
                        <div class="input-group">
                            <textarea style="resize: none;" name="mensagem" rows="3" cols="40" name="mensagem" id="campo-texto"></textarea>
                            <span class="input-group-btn"> <button class="btn bt_bg btn-sm"
                                    id="botao-enviar-mensagem">Enviar</button></span>
                        </div>
                    </form>
                @endif


            </div>
        </div>
    </div>
</div>

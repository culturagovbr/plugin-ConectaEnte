(function ($) {
    var $page = $('#conectaente');
    if (!$page.length) {
        return;
    }

    var $form = $page.find('.conectaente-form');
    var $message = $page.find('.conectaente-message');
    var $token = $form.find('[name=token]');
    var $name = $form.find('[name=name]');
    var $seal = $form.find('[name=seal_id]');
    var $id = $form.find('[name=id]');
    var $hint = $form.find('.js-token-hint');
    var $cancel = $form.find('.js-cancel');

    // o corpo de erro é {error, data}: data é texto (403/404) ou um mapa campo → mensagem
    function errorText(xhr) {
        var body = xhr.responseJSON;
        if (!body || !body.data) {
            return 'Não foi possível concluir a operação (HTTP ' + xhr.status + ').';
        }
        if (typeof body.data === 'string') {
            return body.data;
        }
        return Object.keys(body.data).map(function (field) { return body.data[field]; }).join(' ');
    }

    function show(text, success) {
        $message.text(text).toggleClass('is-success', !!success).prop('hidden', false);
    }

    function resetForm() {
        $form[0].reset();
        $id.val('');
        $seal.find('option[data-editing]').removeAttr('data-editing').prop('disabled', true);
        $form.find('.js-form-title').text('Novo ente federado');
        $hint.text($hint.data('new'));
        $cancel.prop('hidden', true);
    }

    $hint.text($hint.data('new'));

    // sugere o nome a partir do token, sem gravar; o campo continua editável
    $token.on('blur', function () {
        var token = $.trim($token.val());
        if (!token || $id.val() || $.trim($name.val())) {
            return;
        }

        $.post($page.data('urlVerify'), { token: token })
            .done(function (entity) {
                if (entity.name) {
                    $name.val(entity.name);
                }
                $message.prop('hidden', true);
            })
            .fail(function (xhr) { show(errorText(xhr)); });
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        $message.prop('hidden', true);

        var data = { name: $.trim($name.val()), seal_id: $seal.val() };
        var token = $.trim($token.val());
        var editing = $id.val();

        // em branco na edição mantém o token: mandar a chave vazia seria recusado
        if (token || !editing) {
            data.token = token;
        }

        $.ajax({
            url: editing ? $page.find('tr[data-id=' + editing + ']').data('urlUpdate') : $page.data('urlCreate'),
            type: editing ? 'PATCH' : 'POST',
            data: data
        }).done(function () {
            window.location.reload();
        }).fail(function (xhr) {
            show(errorText(xhr));
        });
    });

    $page.on('click', '.js-edit', function () {
        var $row = $(this).closest('tr');
        var sealId = String($row.data('sealId'));

        resetForm();
        $id.val($row.data('id'));
        $name.val($row.data('name'));

        // o selo do próprio ente está desabilitado por "em uso": libera só enquanto edita
        var $own = $seal.find('option[value=' + sealId + ']');
        $own.attr('data-editing', '1').prop('disabled', false);
        $seal.val(sealId);

        $form.find('.js-form-title').text('Editar ente federado');
        $hint.text($hint.data('edit'));
        $cancel.prop('hidden', false);
        $name.focus();
    });

    $cancel.on('click', resetForm);

    $page.on('click', '.js-remove', function () {
        var $row = $(this).closest('tr');

        if (!window.confirm('Remover o ente "' + $row.data('name') + '"? Os editais com o selo deixam de ser enviados.')) {
            return;
        }

        $.ajax({ url: $row.data('urlRemove'), type: 'DELETE' })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { show(errorText(xhr)); });
    });
})(jQuery);

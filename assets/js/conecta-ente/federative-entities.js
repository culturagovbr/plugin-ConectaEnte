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
    var $tokenRequired = $form.find('.js-token-required');
    var $sealToggle = $form.find('.js-seal-toggle');
    var $popover = $form.find('.conectaente-seal-popover');
    var $sealSearch = $form.find('.js-seal-search');
    var $sealButtons = $popover.find('li button');

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

    // o selo de um ente fica desabilitado "em uso"; só o do próprio ente se libera enquanto ele é editado
    function markUsedSeals(ownSealId) {
        $sealButtons.each(function () {
            var $button = $(this);
            $button.prop('disabled', !!$button.data('used') && String($button.data('id')) !== String(ownSealId));
        });
    }

    function chooseSeal(sealId) {
        var $button = $sealButtons.filter(function () { return String($(this).data('id')) === String(sealId); });

        $seal.val(sealId || '');
        $sealToggle.text($button.length ? $button.data('name') : $sealToggle.data('empty'));
        closePopover();
    }

    function closePopover() {
        $popover.prop('hidden', true);
    }

    function filterSeals() {
        var term = $.trim($sealSearch.val()).toLowerCase();
        var visible = 0;

        $sealButtons.each(function () {
            var match = String($(this).data('name')).toLowerCase().indexOf(term) !== -1;
            $(this).parent().prop('hidden', !match);
            visible += match ? 1 : 0;
        });

        $popover.find('.js-seal-none').prop('hidden', visible > 0);
    }

    function openForm(entity) {
        $message.prop('hidden', true);
        $form[0].reset();
        $sealSearch.val('');
        filterSeals();

        var editing = !!entity;
        $id.val(editing ? entity.id : '');
        $name.val(editing ? entity.name : '');
        markUsedSeals(editing ? entity.sealId : null);
        chooseSeal(editing ? entity.sealId : null);

        $form.find('.js-form-title').text(editing ? 'Editar Ente Federado' : 'Cadastrar Ente Federado');
        $hint.text(editing ? $hint.data('edit') : '');
        $tokenRequired.prop('hidden', editing);

        $form.prop('hidden', false);
        $name.focus();
    }

    function closeForm() {
        closePopover();
        $form.prop('hidden', true);
    }

    $page.on('click', '.js-open-form', function () { openForm(null); });
    $form.on('click', '.js-cancel', closeForm);

    $sealToggle.on('click', function () {
        $popover.prop('hidden', !$popover.prop('hidden'));
        if (!$popover.prop('hidden')) {
            $sealSearch.focus();
        }
    });
    $sealSearch.on('input', filterSeals);
    $popover.on('click', 'li button', function () { chooseSeal($(this).data('id')); });
    $(document).on('click', function (event) {
        if (!$(event.target).closest('.conectaente-field').length) {
            closePopover();
        }
    });

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
            url: editing ? $page.find('.conectaente-card[data-id=' + editing + ']').data('urlUpdate') : $page.data('urlCreate'),
            type: editing ? 'PATCH' : 'POST',
            data: data
        }).done(function () {
            window.location.reload();
        }).fail(function (xhr) {
            show(errorText(xhr));
        });
    });

    $page.on('click', '.js-edit', function () {
        var $card = $(this).closest('.conectaente-card');

        openForm({ id: $card.data('id'), name: $card.data('name'), sealId: $card.data('sealId') });
    });

    $page.find('.js-search').on('input', function () {
        var term = $.trim($(this).val()).toLowerCase();
        var visible = 0;

        $page.find('.conectaente-card').each(function () {
            var match = String($(this).data('search')).indexOf(term) !== -1;
            $(this).prop('hidden', !match);
            visible += match ? 1 : 0;
        });

        $page.find('.js-search-none').prop('hidden', visible > 0);
    });

    $page.on('click', '.js-remove', function () {
        var $card = $(this).closest('.conectaente-card');

        if (!window.confirm('Excluir o ente "' + $card.data('name') + '"? Os editais com o selo deixam de ser enviados.')) {
            return;
        }

        $.ajax({ url: $card.data('urlRemove'), type: 'DELETE' })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { show(errorText(xhr)); });
    });
})(jQuery);

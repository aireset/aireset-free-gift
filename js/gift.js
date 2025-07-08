jQuery(function($){
    $(document).on('click', '.aireset-add-gift', function(e){
        e.preventDefault();
        var form = $(this).closest('.aireset-gift-form');
        var productId = form.find('select[name="gift_product_id"]').val();
        $.post(AiresetGiftAjax.ajax_url, {
            action: 'aireset_add_gift_to_cart',
            product_id: productId
        }, function(response){
            if(response.success){
                window.location.reload();
            } else {
                alert(response.data.message || 'Erro ao adicionar brinde');
            }
        });
    });
});

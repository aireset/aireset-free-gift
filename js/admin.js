(function($){
    $(function(){
        var productField = $('#aireset_rule_gifts');
        if (productField.length) {
            productField.select2({
                width: '100%',
                placeholder: productField.data('placeholder') || 'Selecione produtos',
                allowClear: true,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){
                        return {
                            term: params.term,
                            action: 'wc_ajax_json_search_products_and_variations'
                        };
                    },
                    processResults: function(data){
                        var results = [];
                        if(data){
                            $.each(data, function(id, text){
                                results.push({id: id, text: text});
                            });
                        }
                        return {results: results};
                    },
                    cache: true
                }
            });
        }
    });
})(jQuery);

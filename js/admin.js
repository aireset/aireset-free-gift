(function($){
    $(function(){
        function initProductSelect($el){
            if(!$el.length) return;
            $el.select2({
                width: '100%',
                placeholder: $el.data('placeholder') || 'Selecione produtos',
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

        initProductSelect($('#aireset_rule_gifts'));
        initProductSelect($('#aireset_group_a'));
        initProductSelect($('#aireset_group_b'));
    });
})(jQuery);
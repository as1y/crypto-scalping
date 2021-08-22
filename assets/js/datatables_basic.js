/* ------------------------------------------------------------------------------
 *
 *  # Basic datatables
 *
 *  Demo JS code for datatable_basic.html page
 *
 * ---------------------------------------------------------------------------- */


// Setup module
// ------------------------------

var DatatableBasic = function() {


    //
    // Setup module components
    //

    // Basic Datatable examples
    var _componentDatatableBasic = function() {
        if (!$().DataTable) {
            console.warn('Warning - datatables.min.js is not loaded.');
            return;
        }

        // Setting datatable defaults
        $.extend( $.fn.dataTable.defaults, {
            autoWidth: false,

            dom: '<"datatable-header"fl><"datatable-scroll"t><"datatable-footer"ip>',
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Поиск...',
                lengthMenu: '<span>Show:</span> _MENU_',
                paginate: { 'first': 'First', 'last': 'Last', 'next': $('html').attr('dir') == 'rtl' ? '&larr;' : '&rarr;', 'previous': $('html').attr('dir') == 'rtl' ? '&rarr;' : '&larr;' }
            }
        });

        // Basic datatable
        $('.datatable-basic').DataTable({

            pagingType: "simple",
            language: {

                info: "СТРАНИЦА _PAGE_ ИЗ _PAGES_",
                lengthMenu:     "ПОКАЗАТЬ _MENU_ ЗАПИСЕЙ",
                zeroRecords:    "ЗАПИСЕЙ НЕ НАЙДЕНО",
                infoEmpty:      "Показано 0 до 0 из 0 записей",
                infoFiltered:   "(фильтрация из _MAX_ записей)",
                paginate: {
                    "first":      "Первая",
                    "last":       "Последняя",
                    "next":       "Следующая",
                    "previous":   "Предыдущая"
                },
                search:    ""
            },

            order: [[ 0, "desc" ]],






        });



        // Alternative pagination
        $('.datatable-pagination').DataTable({
            pagingType: "simple",
            language: {
                paginate: {'next': $('html').attr('dir') == 'rtl' ? 'Next &larr;' : 'Next &rarr;', 'previous': $('html').attr('dir') == 'rtl' ? '&rarr; Prev' : '&larr; Prev'}
            }
        });

        // Datatable with saving state
        $('.datatable-save-state').DataTable({
            stateSave: true
        });

        // Scrollable datatable
        var table = $('.datatable-scroll-y').DataTable({
            autoWidth: true,
            scrollY: 300
        });

        // Resize scrollable table when sidebar width changes
        $('.sidebar-control').on('click', function() {
            table.columns.adjust().draw();
        });
    };

    // Select2 for length menu styling
    var _componentSelect2 = function() {
        if (!$().select2) {
            console.warn('Warning - select2.min.js is not loaded.');
            return;
        }

        // Initialize
        $('.dataTables_length select').select2({
            minimumResultsForSearch: Infinity,
            dropdownAutoWidth: true,
            width: 'auto'
        });
    };


    //
    // Return objects assigned to module
    //

    return {
        init: function() {
            _componentDatatableBasic();
            _componentSelect2();
        }
    }
}();


// Initialize module
// ------------------------------

document.addEventListener('DOMContentLoaded', function() {
    DatatableBasic.init();
});

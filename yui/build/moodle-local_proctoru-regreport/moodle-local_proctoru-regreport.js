YUI.add('moodle-local_proctoru-regreport', function (Y, NAME) {

M.local_proctoru = M.local_proctoru || {};
M.local_proctoru.regreport = {
  init: function(data) {

    var table = new Y.DataTable({
        columns:    ['lastname','firstname', 'username','idnumber', 'major', 'college','status', 'role'],
        data:       data,
        sortable:   true,
        scrollable: 'y',
        height:     '600px'
    });
    
    table.render('#report');
  }
};

}, '@VERSION@', {"requires": ["datatable", "datatable-scroll"]});

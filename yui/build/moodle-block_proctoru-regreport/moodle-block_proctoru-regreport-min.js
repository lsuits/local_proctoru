YUI.add("moodle-block_proctoru-regreport",function(e,t){M.block_proctoru=M.block_proctoru||{},M.block_proctoru.regreport={init:function(t){var n=new e.DataTable({columns:["lastname","firstname","username","idnumber","major","college","status","role"],data:t,sortable:!0,scrollable:"y",height:"600px"});n.render("#report")}}},"@VERSION@",{requires:["datatable","datatable-scroll"]});

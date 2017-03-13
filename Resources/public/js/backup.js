/**
 * SNBundle
 * Created by PhpStorm.
 * File:
 * User: thomas
 * Date: 10.03.17
 * Time: 21:19
 */
$(document).ready(function() {
  $('#delete').on('show.bs.modal', function(e) {
    var btn = $(e.relatedTarget);
    var id = btn.data("id");
    var name = btn.data("name");

    $(this).find('#name').text(name);
    $(this).find('#timestamp').val(id);
  });
});
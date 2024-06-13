<?php

// Create Group Shortcode
function gem_create_group_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to create a group.';
    }

    $output = '<div class="modal fade" id="create-group-modal" tabindex="-1" role="dialog">
                   <div class="modal-dialog" role="document">
                       <div class="modal-content">
                           <div class="modal-header">
                               <h5 class="modal-title">Create Group</h5>
                               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                   <span aria-hidden="true">&times;</span>
                               </button>
                           </div>
                           <div class="modal-body">
                           <p><strong>You will be a part of the group by default.</strong></p> <!-- Displaying the message in bold -->
                               <form id="create-group-form">
                                   <div class="form-group">
                                       <label for="group-name">Group Name:</label>
                                       <input type="text" id="group-name" class="form-control" placeholder="Enter group name" required>
                                   </div>
                                   <div id="members-container">
                                       <div class="form-group">
                                           <label for="group-member">Member:</label>
                                           <input type="email" name="group-member" class="form-control" placeholder="Enter email address" required>
                                       </div>
                                   </div>
                                   <button type="button" id="add-member-btn" class="btn btn-secondary">Add Member</button>
                                   <button type="submit" class="btn btn-primary">Create Group</button>
                               </form>
                               <div id="create-group-response"></div>
                           </div>
                       </div>
                   </div>
               </div>
               <script>
                   jQuery(document).ready(function($) {
                       // Function to clear form fields
                       function clearForm() {
                           $("#group-name").val("");
                           $("input[name=\'group-member\']").val("");
                       }

                       // Reset form data when modal is shown
                       $("#create-group-modal").on("shown.bs.modal", function () {
                           clearForm();
                       });

                       $("#create-group-form").submit(function(event) {
                           event.preventDefault();
                           var groupName = $("#group-name").val();
                           var members = [];
                           $("input[name=\'group-member\']").each(function() {
                               if ($(this).val().trim() !== "") {
                                   members.push($(this).val());
                               }
                           });
                           $.ajax({
                               url: "' . admin_url('admin-ajax.php') . '",
                               method: "POST",
                               data: {
                                   action: "gem_create_group",
                                   group_name: groupName,
                                   members: members
                               },
                               success: function(response) {
                                   if (response.success) {
                                       $("#create-group-response").html(response.data);
                                       $("#create-group-modal").modal("hide"); // Close the modal upon success
                                   } else {
                                       $("#create-group-response").html("Error: " + response.data);
                                   }
                               },
                               error: function(response) {
                                   $("#create-group-response").html("Error: " + response.responseText);
                               }
                           });
                       });

                       $("#add-member-btn").click(function() {
                           $("#members-container").append(\'<div class="form-group"><label for="group-member">Member:</label><input type="email" name="group-member" class="form-control" placeholder="Enter email address" required></div>\');
                       });

                       // Show modal when button is clicked
                       $("#create-group-btn").click(function() {
                           $("#create-group-modal").modal("show");
                       });
                   });
               </script>';

    return $output;
}

?>

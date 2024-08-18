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
                                       <input type="text" id="group-name" class="form-control" placeholder="Enter group name" required maxlength="20">
                                       <div id="group-name-error" class="text-danger"></div>
                                   </div>
                                   <div class="form-group">
                                       <label for="local-currency">Local Currency:</label>
                                       <input type="text" id="local-currency" class="form-control" placeholder="Enter local currency" required maxlength="20">
                                       <div id="local-currency-error" class="text-danger"></div>
                                   </div>
                                   <div id="members-container">
                                       <div class="form-group">
                                           <label for="group-member">Member:</label>
                                           <input type="email" name="group-member" class="form-control group-member-input" placeholder="Enter email address" required>
                                           <div class="group-member-error text-danger"></div>
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

                       // Function to validate group name (No $ sign and max 20 characters)
                       function validateGroupName(groupName) {
                           if (groupName.length > 20) {
                               $("#group-name-error").text("Group name must be less than 20 characters.");
                               return false;
                           }
                           if (groupName.includes("$")) {
                               $("#group-name-error").text("Group name must not contain the $ sign.");
                               return false;
                           }
                           $("#group-name-error").text(""); // Clear error message
                           return true;
                       }

                       // Function to validate local currency (Max 20 characters)
                       function validateLocalCurrency(localCurrency) {
                           if (localCurrency.length > 20) {
                               $("#local-currency-error").text("Local currency must be less than 20 characters.");
                               return false;
                           }
                           $("#local-currency-error").text(""); // Clear error message
                           return true;
                       }

                       // Function to validate and collect members
                       function validateAndCollectMembers() {
                           var members = [];
                           var valid = true;
                           var emails = {};

                           $("input[name=\'group-member\']").each(function() {
                               var email = $(this).val().trim().toLowerCase(); // Convert to lowercase
                               var emailInput = $(this);
                               var emailError = $(this).next(".group-member-error");

                               // Validate if email is empty
                               if (email === "") {
                                   emailError.text("Email cannot be empty.");
                                   valid = false;
                                   return false; // Exit loop
                               }

                               // Validate email format
                               var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
                               if (!emailPattern.test(email)) {
                                   emailError.text("Invalid email format.");
                                   valid = false;
                                   return false; // Exit loop
                               }

                               // Check for duplicate emails
                               if (emails[email]) {
                                   emailError.text("Duplicate email found.");
                                   valid = false;
                                   return false; // Exit loop
                               } else {
                                   emails[email] = true; // Mark email as seen
                                   members.push(email); // Add email to the members list
                                   emailError.text(""); // Clear error message
                               }
                           });

                           return valid ? members : null;
                       }

                       // Function to clear form fields and remove extra member inputs
                       function clearForm() {
                           $("#group-name").val("");
                           $("#local-currency").val("");
                           $(".group-member-input").val(""); // Clear the first input
                           $(".group-member-error").text(""); // Clear any email error messages
                           $("#group-name-error").text(""); // Clear group name error
                           $("#local-currency-error").text(""); // Clear currency error
                           
                           // Remove any additional member inputs (except the first one)
                           $("#members-container .form-group").not(":first").remove();
                       }

                       // Reset form data when modal is hidden
                       $("#create-group-modal").on("hidden.bs.modal", function () {
                           clearForm(); // Clear form and remove extra member fields
                       });

                       $("#create-group-form").submit(function(event) {
                           event.preventDefault();
                           var groupName = $("#group-name").val();
                           var localCurrency = $("#local-currency").val();

                           // Validate group name and local currency
                           if (!validateGroupName(groupName) || !validateLocalCurrency(localCurrency)) {
                               return;
                           }

                           // Validate members and check for duplication
                           var members = validateAndCollectMembers();
                           if (!members) {
                               return; // If validation fails, stop the submission
                           }

                           $.ajax({
                               url: "' . admin_url('admin-ajax.php') . '",
                               method: "POST",
                               data: {
                                   action: "gem_create_group",
                                   group_name: groupName,
                                   local_currency: localCurrency,
                                   members: members
                               },
                               success: function(response) {
                                   if (response.success) {
                                       $("#create-group-response").html("Group creation successful!");
                                       setTimeout(function() {
                                           window.location.href = "' . site_url('/my-groups') . '"; // Redirect after success
                                       }, 2000);
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
                           $("#members-container").append(\'<div class="form-group"><label for="group-member">Member:</label><input type="email" name="group-member" class="form-control group-member-input" placeholder="Enter email address" required><div class="group-member-error text-danger"></div></div>\');
                       });

                       // Show modal when button is clicked
                       $("#create-group-btn").click(function() {
                           $("#create-group-modal").modal("show");
                       });
                   });
               </script>';

    return $output;
}

add_shortcode('gem_create_group', 'gem_create_group_shortcode');
?>

<?php

// Create Group Shortcode
function gem_create_group_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to create a group.';
    }

    $output = '<div class="modal fade" id="create-group-modal" tabindex="-1" role="dialog">
                   <div class="modal-dialog modal-dialog-centered" role="document">
                       <div class="modal-content">
                           <div class="modal-header">
                               <h5 class="modal-title">Create Group</h5>
                               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                   <span aria-hidden="true">&times;</span>
                               </button>
                           </div>
                           <div class="modal-body">
                               <p><strong>You will be a part of the group by default.</strong></p>
                               <form id="create-group-form">
                                   <div class="form-row">
                                       <div class="form-group col-md-12">
                                           <label for="group-name">Group Name:</label>
                                           <input type="text" id="group-name" class="form-control" placeholder="Enter group name" required maxlength="20">
                                           <div id="group-name-error" class="text-danger"></div>
                                       </div>
                                   </div>
                                   <div class="form-row">
                                       <div class="form-group col-md-12">
                                           <label for="local-currency">Local Currency:</label>
                                           <input type="text" id="local-currency" class="form-control" placeholder="Enter local currency" required maxlength="20">
                                           <div id="local-currency-error" class="text-danger"></div>
                                       </div>
                                   </div>
                                   <div id="members-container" class="form-row" style="max-height: 300px; overflow-y: auto;"></div> <!-- Scrollable area -->
                                   <div class="form-row">
                                       <div class="col-6">
                                           <button type="button" id="add-member-btn" class="btn btn-secondary btn-block">Add Member</button>
                                       </div>
                                       <div class="col-6">
                                           <button type="submit" class="btn btn-primary btn-block">Create Group</button>
                                       </div>
                                   </div>
                                   <!-- Spinner for processing state -->
                                   <div id="spinner" class="spinner-border text-primary d-none mt-3" role="status">
                                       <span class="sr-only">Loading...</span>
                                   </div>
                               </form>
                           </div>
                       </div>
                   </div>
               </div>
               <script>
                   jQuery(document).ready(function($) {
                       var currentUserEmail = "' . esc_js(wp_get_current_user()->user_email) . '";

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

                       // Function to validate and collect members, skip empty fields
                       function validateAndCollectMembers() {
                           var members = [];
                           var valid = true;
                           var emails = {};
                           var duplicateAlertShown = false;

                           $("input[name=\'group-member\']").each(function() {
                               var email = $(this).val().trim().toLowerCase();
                               var emailError = $(this).next(".group-member-error");

                               if (email === "") {
                                   // Skip empty fields
                                   return true;
                               }

                               var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
                               if (!emailPattern.test(email)) {
                                   emailError.text("Invalid email format.");
                                   valid = false;
                                   return false;
                               }

                               if (emails[email]) {
                                   if (!duplicateAlertShown) {
                                       alert("Duplicate email found: " + email);
                                       duplicateAlertShown = true;
                                   }
                                   emailError.text("Duplicate email found.");
                                   valid = false;
                                   return false;
                               } else {
                                   emails[email] = true;
                                   members.push(email);
                                   emailError.text("");
                               }
                           });

                           return valid ? members : null;
                       }

                       // Function to clear form fields and suggestions
                       function clearFormAndSuggestions() {
                           $("#group-name").val("");
                           $("#local-currency").val("");
                           $(".group-member-input").val("");  // Clear member inputs
                           $(".group-member-error").text(""); // Clear any error messages
                           $("#group-name-error").text("");   // Clear group name errors
                           $("#local-currency-error").text(""); // Clear currency errors
                           $(".suggestion-box").hide().empty(); // Clear any suggestion boxes

                           // Clear all dynamically added member inputs
                           $("#members-container").empty();
                       }

                       // Reset form when modal is hidden
                       $("#create-group-modal").on("hidden.bs.modal", function () {
                           clearFormAndSuggestions();
                           $("#create-group-modal").removeClass("show").css("display", "none");
                       });

                       // Form submission
                       $("#create-group-form").submit(function(event) {
                           event.preventDefault();
                           var groupName = $("#group-name").val();
                           var localCurrency = $("#local-currency").val();

                           if (!validateGroupName(groupName) || !validateLocalCurrency(localCurrency)) {
                               return;
                           }

                           var members = validateAndCollectMembers();
                           if (!members) {
                               return;
                           }

                           $("#spinner").removeClass("d-none");

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
                                   $("#spinner").addClass("d-none");

                                   if (response.success) {
                                       $("#create-group-modal").modal("hide");
                                       alert("Group created successfully!");

                                       setTimeout(function() {
                                           location.reload();
                                       }, 500);
                                   } else {
                                       alert("Error: " + response.data);
                                   }
                               },
                               error: function(response) {
                                   $("#spinner").addClass("d-none");
                                   alert("Error: " + response.responseText);
                               }
                           });
                       });

                       // Add new member input field
                       $("#add-member-btn").click(function() {
                           var memberHtml = \'<div class="form-group col-md-12"><label for="group-member">Member:</label>\' +
                                            \'<input type="text" name="group-member" class="form-control group-member-input" placeholder="Search member by name or email">\'+
                                            \'<div class="suggestion-box" style="display:none; background: #fff; border: 1px solid #ccc; z-index: 10; max-height: 150px; overflow-y: auto;"></div>\'+
                                            \'<div class="group-member-error text-danger"></div></div>\';
                           $("#members-container").append(memberHtml);
                       });

                       // Search for members
                       $(document).on("input", ".group-member-input", function() {
                           var search_term = $(this).val().trim().toLowerCase();
                           var $input = $(this);

                           if (search_term.length >= 3) {
                               $.ajax({
                                   url: "' . admin_url('admin-ajax.php') . '",
                                   method: "POST",
                                   data: {
                                       action: "gem_search_members",
                                       search_term: search_term
                                   },
                                   success: function(response) {
                                       if (response.success && response.data.length > 0) {
                                           var suggestions = "";
                                           $.each(response.data, function(index, user) {
                                               if (user.email.toLowerCase() !== currentUserEmail) {
                                                   suggestions += \'<button type="button" class="btn btn-sm btn-info suggestion-item" data-email="\' + user.email + \'">\' + user.name + \' - \' + user.email + \'</button><br>\';
                                               }
                                           });
                                           $input.siblings(".suggestion-box").html(suggestions).show();
                                       } else {
                                           $input.siblings(".suggestion-box").html("<p>No results found</p>").show();
                                       }
                                   },
                                   error: function() {
                                       $input.siblings(".suggestion-box").html("<p>Error searching members</p>").show();
                                   }
                               });
                           } else {
                               $input.siblings(".suggestion-box").hide();
                           }
                       });

                       // Handle suggestion click
                       $(document).on("click", ".suggestion-item", function() {
                           var email = $(this).data("email");
                           var $input = $(this).closest(".form-group").find(".group-member-input");
                           $input.val(email);
                           $input.siblings(".suggestion-box").hide();
                       });

                       // Show modal when "Create Group" is clicked
                       $("#create-group-btn").click(function() {
                           $("#create-group-modal").modal("show");
                       });
                   });
               </script>';

    return $output;
}

add_shortcode('gem_create_group', 'gem_create_group_shortcode');
?>

<div class="gp-general-settings-wrapper">
    <h1>GrabPress Account Configuration</h1>
    <p>Here you can fully setup an account to monetize the video feeds you create.</p>
    <div id="accordion">  
        <h3 id="gp-account-setup">
            <span>Account Setup </span>
        </h3>

        <fieldset id="account">
            <?php
            // If we have a user, we show the unlink button
            if (  isset($request['linked']) && $request['linked'] === '1' && isset($request['request']['action']) && ($request['request']['action'] == 'create-user' || $request['request']['action'] == 'link-user')  ) {
                ?>
                <p id="is_linked_to" class="account-help">This installation is linked to: <?php echo isset($request['user']->user->email) ? $request['user']->user->email : $request['user']->email; ?></p>
                <form method="post">
                    <input name="confirm" value="yes" type="hidden" />
                    <input name="action" value="unlink" type="hidden" />
                    <input name="submit" class="button-primary unlink-account" id="gp-unlink-account" value="Unlink this account" type="submit" />
                </form>
            <?php } else { ?>
                <div class="gp-general-settings-subtitle">To earn revenue from GrabPress pre-roll ads, create an account with the form below.</div>
                <div class="gp-general-settings-subtitle">The account will then be linked to this GrabPress installation.</div>
                <div class="switchtomanual">
                    <input class="switchtomanual-checkbox" name="manual_uastring" id="switchtomanual" type="checkbox" checked>
                    <label for="switchtomanual">Already have a publisher</label>     
                </div>

                <div class="wrapper-link-existing" id="uastring_automatic">
                    <!--<p>You can register for a Grab Media publisher account at a later time if you're interested in earning revenue from GrabPress pre-roll ads </p>
                    -->
                    <form id="link-existing" method="post">
                        <input id="action-link-user" type="hidden" name="action" value="link-user" />
                        <label>Email</label> <input name="email" id="id_email" type="text" value="<?php echo $email = ( isset($request['email']) && $request['email'] != null ) ? esc_attr($request['email']) : ''; ?>" />

                        <label>Password</label> <input name="password" id="password" type="password" />
                        <p class = "account-help"><a href="http://www.grab-media.com/publisherAdmin/forgotpw" target="_blank">Forgot password?</a>

                        <div class="alignright"><input class="button-primary link-account" name="submit" value="Link Account" type="submit"></div>
                    </form>
                </div>        
                <div  id="uastring_manual">
                    <?php echo Grabpress::render('includes/account/forms/create.php', array('request' => $request['request'])); ?>
                </div> 

            </fieldset>
        <?php } ?>

    </div>
</div>
<script type="text/javascript">
    // Init the accordion
    (function ($) {

        $("#accordion").accordion({
            icons: {
                "header": "ui-icon-triangle-1-s",
                "activeHeader": " ui-icon-triangle-1-n"
            },
            active: 0,
            animate: 200,
            heightStyle: "content",
            collapsible: true
        });
        //Hit the check box in case of create
<?php if (isset($request['request']['action']) && $request['request']['action'] == 'create' || $request['request']['action'] == 'default') :
    ?>

            var account_switch = $("#switchtomanual"),
                    link_existing = $('.wrapper-link-existing');

    <?php if ($request['request']['action'] !== 'default') : ?>
                //Show create account  form & hide account linking.
                account_switch.click();
                link_existing.hide();
    <?php endif; ?>

            //click account check box
            account_switch.on('click', function () {

                if ($(this).prop('checked') === false) {
                    //Show link account form
                    link_existing.show();
                } else {
                    //Hide link account form
                    link_existing.hide();
                }

            });

    <?php if (isset($request['request']['action']) && $request['request']['action'] !== 'default') : ?>
                //Scroll down to let the user see the result of his action
                $("html, body").animate({scrollTop: $(document).height() - $(window).height()});

    <?php endif; ?>

<?php endif; ?>


        //Account setup
        $('#switchtomanual').change(function () {
            var headers = $('#accordion h3'),
                    //Account setup tab, last element of array
                    acount_header = headers[headers.length - 1];
            if ($('#switchtomanual').is(':checked')) {
                $('#uastring_manual').hide();
                $('#uastring_automatic').css('display', 'block');
            } else {
                $('#uastring_manual').show('fast');
                $('#uastring_automatic').css('display', 'none');
            }
        }).change();

    })(jQuery);


</script>
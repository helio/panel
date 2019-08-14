const loadList = function (endpoint, containerSelector, startFromTheBeginning = false) {
    let container = $(containerSelector);
    if (startFromTheBeginning) container.removeClass('done');
    if (container.hasClass('loading') || container.hasClass('done')) return;

    container.addClass('loading', true);

    let offset = container.data('loaded');
    let limit = 5;
    if (typeof offset === 'undefined') {
        offset = 0;
    }

    if (startFromTheBeginning) {
        container.data('loaded', 0);
        offset = 0;
        container.find('.list-group-item.loadable').remove();
    }

    let settings = {limit: limit, offset: offset};
    if (container.data('orderby')) {
        settings.orderby = container.data('orderby');
    }
    $.ajax({
        accepts: {
            mycustomtype: 'application/json'
        },
        url: '/api/' + endpoint,
        data: settings,
        success: function (data) {

            if (data.hasOwnProperty('items')) {
                if (data.items.length < limit) {
                    container.addClass('done');
                }

                if (data.items.length > 0) {
                    data.items.forEach(function (item) {
                        if (!data.items.hasOwnProperty(item)) {
                            $(item.html).appendTo(containerSelector);
                        }
                    });
                    container.data('loaded', offset + data.items.length);
                }

                // move loading trigger and indicator to the end of the list again
                $(containerSelector + ' > .loader').appendTo(container);
                $(containerSelector + ' > .loadmore').appendTo(container);

                // done loading
                container.removeClass('loading');
                container.trigger('moreLoaded');
            }
        }
    });
};

function initActionButtons(item) {

    // click the list-view heading then expand a row
    item.find(".list-group-item-header").click(function (event) {
        if (!$(event.target).is("button, a, input, .fa-ellipsis-v")) {
            $(this).find(".fa-angle-right").toggleClass("fa-angle-down")
                .end().parent().toggleClass("list-view-pf-expand-active")
                .find(".list-group-item-container").toggleClass("hidden");
        }
    });

    // click the close button, hide the expand row and remove the active status
    item.find(".list-group-item-container .close").on("click", function () {
        $(this).parent().addClass("hidden")
            .parent().removeClass("list-view-pf-expand-active")
            .find(".fa-angle-right").removeClass("fa-angle-down");
    });

    item.find(".list-view-pf-actions:not(.hidden) .dropdown-menu a[data-action]").on('click', function (e) {
        e.preventDefault();
        item.addClass('loading');

        let method = $(this).data('method') ? $(this).data('method') : 'put';
        let data = {};
        data[item.data('idname')] = item.data('id');
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/' + $(this).data('action'),
            data: data,
            method: method,
            success: function (data) {
                if (method === 'delete' && data.hasOwnProperty('removed') && !!data['removed']) {
                    item.addClass('hidden');
                }

                if (data.hasOwnProperty('listItemHtml')) {
                    let newContent = $(data.listItemHtml);
                    initActionButtons(newContent);
                    item.replaceWith(newContent);
                }

                if (data.hasOwnProperty('notification')) {
                    $(data['notification']).prependTo($('#notificationContainer'));
                }
            },
            complete: function () {
                item.removeClass('loading');
            }
        })
    });

    item.find('.try-job').on('submit', function (e) {
        let button = $(this).find('button[type="submit"]');
        e.preventDefault();
        let request = new XMLHttpRequest();
        request.open("POST", $(this).data('demourl'), true);
        request.onload = function () {
            if (request.readyState === 4 && request.status === 200) {
                $('<div class="alert alert-success">Now wait for the result. You can check in by reloading.</div>').insertAfter(button);
            }
        };
        request.send(new FormData(e.target));
    });
}


function filterItemsByTextSearch(element) {
    let value = $(element).val();
    let itemSelector = $(element).data('itemSelector');
    let textSelector = $(element).data('textSelector');

    $(itemSelector).each(function () {
        if (!value || $(this).find(textSelector).text().search(new RegExp(value, 'i')) > -1) {
            $(this).show();
        }
        else {
            $(this).hide();
        }
    });
}

const wizard = function (id) {
    let self = this, modal, tabs, tabCount, tabLast, currentGroup, currentTab;
    self.id = id;

    $(self.id).click(function () {
        self.init(this)
    });

    this.init = function (button) {
        // get id of open modal
        self.modal = $(button).data("target");

        self.resetToInitialState();

        // open modal
        $(self.modal).modal('show');

        // assign data attribute to all tabs
        $(self.modal + " .wizard-pf-sidebar .list-group-item").each(function () {
            // set the first digit (i.e. n.0) equal to the index of the parent tab group
            // set the second digit (i.e. 0.n) equal to the index of the tab within the tab group
            $(this).attr("data-tab", ($(this).parent().index() + 1 + ($(this).index() / 10 + .1)));
        });
        // assign data attribute to all tabgroups
        $(self.modal + " .wizard-pf-sidebar .list-group").each(function () {
            // set the value equal to the index of the tab group
            $(this).attr("data-tabgroup", ($(this).index() + 1));
        });

        // assign data attribute to all step indicator steps
        $(self.modal + " .wizard-pf-steps-indicator  .wizard-pf-step").each(function () {
            // set the value equal to the index of the tab group
            $(this).attr("data-tabgroup", ($(this).index() + 1));
        });
        // assign data attribute to all step indicator substeps
        $(self.modal + " .wizard-pf-steps-indicator .wizard-pf-step-title-substep").each(function () {
            // set the first digit (i.e. n.0) equal to the index of the parent tab group
            // set the second digit (i.e. 0.n) equal to the index of the tab within the tab group
            $(this).attr("data-tab", ($(this).parent().parent().index() + 1 + (($(this).index() - 2) / 10 + .1)));
        });

        // assign data attribute to all alt step indicator steps
        $(self.modal + " .wizard-pf-steps-alt .wizard-pf-step-alt").each(function () {
            // set the value equal to the index of the tab group
            let tabGroup = $(this).index() + 1;
            $(this).attr("data-tabgroup", tabGroup);
            $(this).find('.wizard-pf-step-alt-substep').each(function () {
                $(this).attr("data-tab", (tabGroup + (($(this).index() + 1) / 10)));
            });
        });

        // assign active and hidden states to the steps alt classes
        $(self.modal + " .wizard-pf-steps-alt-indicator").removeClass('active');
        $(self.modal + " .wizard-pf-steps-alt").addClass('hidden');
        $(self.modal + " .wizard-pf-steps-alt-indicator").on('click', function () {
            $(self.modal + ' .wizard-pf-steps-alt-indicator').toggleClass('active');
            $(self.modal + ' .wizard-pf-steps-alt').toggleClass('hidden');
        });
        $(self.modal + " .wizard-pf-step-alt > ul").addClass("hidden");

        // create array of all tabs, using the data attribute, and determine the last tab
        self.tabs = $(self.modal + " .wizard-pf-sidebar .list-group-item").map(function () {
                return $(this).data("tab");
            }
        );
        self.tabCount = self.tabs.length;
        self.tabSummary = self.tabs[self.tabCount - 2]; // second to last tab displays summary
        self.tabLast = self.tabs[self.tabCount - 1]; // last tab displays progress
        // set first tab group and tab as current tab
        // if someone wants to target a specific tab, that could be handled here
        self.currentGroup = 1;
        self.currentTab = 1.1;

        $.ajax({
            url: '/api/' + $(self.modal).data('endpoint'),
            dataType: 'json',
            method: 'post',
            success: function (data) {
                $(self.modal).data($(self.modal).data('idName'), data['id']);
                if (data.hasOwnProperty('token')) {
                    $(self.modal).data('token', data['token']);
                }
                $(self.modal + " .wizard-pf-loading").addClass("hidden");
                // show tabs and tab groups
                $(self.modal + " .wizard-pf-steps").removeClass("hidden");
                $(self.modal + " .wizard-pf-sidebar").removeClass("hidden");
                // remove active class from all tabs
                $(self.modal + " .wizard-pf-sidebar .list-group-item.active").removeClass("active");

                self.updateToCurrentPage();
            }
        });


        //initialize click listeners
        self.tabGroupSelect();
        self.tabSelect();
        self.altStepClick();
        self.altSubStepClick();
        self.backBtnClicked();
        self.nextBtnClicked();
        self.cancelBtnClick();
        self.disableDefaultClick();

        // Listen for required value change
        self.requiredChange();
        self.selectChange();
        self.switchChange();

        // Handle form submit event
        self.formSubmitted();
    };

    this.parseDataIntoHtml = function () {
        $(self.modal + " [data-parse='true']").each(function () {
            $(this).html($(this).html()
                .replace('$serverId', $(self.modal).data('serverid'))
                .replace('$serverToken', $(self.modal).data('token'))
            );
        });
    };

    // update which tab group is active
    this.updateTabGroup = function () {
        $(self.modal + " .wizard-pf-step.active").removeClass("active");
        $(self.modal + " .wizard-pf-step[data-tabgroup='" + self.currentGroup + "']").addClass("active");
        $(self.modal + " .wizard-pf-sidebar .list-group").addClass("hidden");
        $(self.modal + " .list-group[data-tabgroup='" + self.currentGroup + "']").removeClass("hidden");
        $(self.modal + " .wizard-pf-step-alt")
            .not("[data-tabgroup='" + self.currentGroup + "']").removeClass("active").end()
            .filter("[data-tabgroup='" + self.currentGroup + "']").addClass("active");
        $(self.modal + " .wizard-pf-step-alt > ul").addClass("hidden");
        $(self.modal + " .wizard-pf-step-alt[data-tabgroup='" + self.currentGroup + "'] > ul").removeClass("hidden");
    };

    // enable a button
    this.enableBtn = function ($el) {
        $el.removeClass("disabled").removeAttr("disabled");
    };

    // disable a button
    this.disableBtn = function ($el) {
        $el.addClass("disabled").attr("disabled", "disabled");
    };

    // update which tab is active
    this.updateActiveTab = function () {
        // mark all previous tabs as done
        let currentDoneTab = self.currentTab - 1;
        while (currentDoneTab > 0) {
            $(self.modal + " .list-group-item[data-tab='" + currentDoneTab + "']").addClass("done");
            $(self.modal + " .wizard-pf-steps-indicator .wizard-pf-step-title-substep[data-tab='" + currentDoneTab + "']").addClass("done");
            $(self.modal + " .wizard-pf-step-alt .wizard-pf-step-alt-substep[data-tab='" + currentDoneTab + "']").addClass("done");
            currentDoneTab--;
        }

        $(self.modal + " .list-group-item.active").removeClass("active");
        $(self.modal + " .list-group-item[data-tab='" + self.currentTab + "']").addClass("active");

        // Update steps indicator to handle mobile mode
        $(self.modal + " .wizard-pf-steps-indicator .wizard-pf-step-title-substep").removeClass("active");
        $(self.modal + " .wizard-pf-steps-indicator .wizard-pf-step-title-substep[data-tab='" + self.currentTab + "']").addClass("active");

        // Update steps alt indicator to handle mobile mode
        $(self.modal + " .wizard-pf-step-alt .wizard-pf-step-alt-substep").removeClass("active");
        $(self.modal + " .wizard-pf-step-alt .wizard-pf-step-alt-substep[data-tab='" + self.currentTab + "']").addClass("active");

        self.updateVisibleContents();
    };

    // update which contents are visible
    this.updateVisibleContents = function () {
        let tabIndex = ($.inArray(self.currentTab, self.tabs));
        // displaying contents associated with currentTab
        $(self.modal + " .wizard-pf-contents").addClass("hidden");
        $(self.modal + " .wizard-pf-contents:eq(" + tabIndex + ")").removeClass("hidden");
        // setting focus to first form field in active contents
        setTimeout(function () {
            $(".wizard-pf-contents:not(.hidden) form input, .wizard-pf-contents:not(.hidden) form textarea, .wizard-pf-contents:not(.hidden) form select").first().focus(); // this does not account for disabled or read-only inputs
        }, 100);
    };

    // update display state of Back button
    this.updateBackBtnDisplay = function () {
        let $backBtn = $(self.modal + " .wizard-pf-back");
        // noinspection EqualityComparisonWithCoercionJS
        if (self.currentTab == self.tabs[0]) {
            self.disableBtn($backBtn)
        } else {
            self.enableBtn($backBtn)
        }
    };

    // update display state of next/finish button
    this.updateNextBtnDisplay = function () {
        // noinspection EqualityComparisonWithCoercionJS
        if (self.currentTab == self.tabSummary) {
            $(self.modal + " .wizard-pf-next").focus().find(".wizard-pf-button-text").text("Confirm");
        } else {
            $(self.modal + " .wizard-pf-next .wizard-pf-button-text").text("Next");
        }
    };

    // update display state of buttons in the footer
    this.updateWizardFooterDisplay = function () {
        self.updateBackBtnDisplay();
        self.updateNextBtnDisplay();
    };


    this.updateToCurrentPage = function () {
        self.parseDataIntoHtml();
        self.updateTabGroup();
        self.updateActiveTab();
        self.updateVisibleContents();
        self.updateWizardFooterDisplay();
    };

    // when the user clicks a step, then the tab group for that step is displayed
    this.tabGroupSelect = function () {
        $('body').on('click', self.modal + " .wizard-pf-step:not(.disabled) > a", function () {
            self.currentGroup = $(this).parent().data("tabgroup");
            // update value for currentTab -- if a tab is already marked as active
            // for the new tab group, use that, otherwise set it to the first tab
            // in the tab group
            self.currentTab = $(self.modal + " .list-group[data-tabgroup='" + self.currentGroup + "'] .list-group-item.active").data("tab");
            if (self.currentTab === undefined) {
                self.currentTab = self.currentGroup + 0.1;
            }

            self.updateToCurrentPage();
        });
    };

    // when the user clicks a tab, then the tab contents are displayed
    this.tabSelect = function () {
        $('body').on('click', self.modal + " .wizard-pf-sidebar .list-group-item:not(.disabled) > a", function () {
            // update value of currentTab to new active tab
            self.currentTab = $(this).parent().data("tab");
            self.updateToCurrentPage();
        });
    };

    this.altStepClick = function () {
        $(self.modal + " .wizard-pf-step-alt").each(function () {
            let $this = $(this);
            $(this).find('> a').on('click', function () {
                let subStepList = $this.find('> ul');
                if (subStepList && (subStepList.length > 0)) {
                    $this.find('> ul').toggleClass('hidden');
                } else {
                    self.currentGroup = $this.data("tabgroup");
                }
            });
        });
    };

    this.altSubStepClick = function () {
        $('body').on('click', self.modal + " .wizard-pf-step-alt .wizard-pf-step-alt-substep:not(.disabled) > a", function () {
            // update value of currentTab to new active tab
            self.currentTab = $(this).parent().data("tab");
            self.currentGroup = $(this).parent().parent().parent().data("tabgroup");
            self.updateToCurrentPage();
        });
    };

    // Back button clicked
    this.backBtnClicked = function () {
        $('body').on('click', self.modal + " .wizard-pf-back", function () {
            // if not the first page
            // noinspection EqualityComparisonWithCoercionJS
            if (self.currentTab != self.tabs[0]) {
                // go back a page (i.e. -1)
                self.wizardPaging(-1);
                // show/hide/disable/enable buttons if needed
                self.updateWizardFooterDisplay();
            }
        });
    };

    // Next button clicked
    this.nextBtnClicked = function () {
        $('body').on('click', self.modal + " .wizard-pf-next", function () {
            // noinspection EqualityComparisonWithCoercionJS
            if (self.currentTab == self.tabSummary) {
                self.wizardPaging(1);
                self.finish();
            } else {
                // go forward a page (i.e. +1)
                self.wizardPaging(1);
                // show/hide/disable/enable buttons if needed
                self.updateWizardFooterDisplay();
            }
        });
    };

    // Form submitted
    this.formSubmitted = function () {
        $(self.modal + ' form').on('submit', function (e) {
            e.preventDefault();
            $('button[type="submit"]:not(.disabled)').trigger('click');
        });
    };

    // Disable click events
    this.disableDefaultClick = function () {
        $(self.modal + " .wizard-pf-step > a")
            .add(self.modal + " .wizard-pf-step-alt > a")
            .add(self.modal + " .wizard-pf-step-alt .wizard-pf-step-alt-substep > a")
            .add(self.modal + " .wizard-pf-sidebar .list-group-item > a").on('click', function (e) {
            e.preventDefault()
        });
    };

    this.validateRequired = function ($el) {
        let $nextBtn = $(self.modal + " .wizard-pf-next"),
            $step = $(self.modal + " .wizard-pf-step"),
            $stepAltSubStep = $(self.modal + " .wizard-pf-step-alt-substep:not(.wizard-pf-progress-link):not(.done)"),
            $sidebarItem = $(self.modal + " .wizard-pf-sidebar .list-group-item:not(.wizard-pf-progress-link):not(.done)");

        if ($el.val()) {
            $stepAltSubStep.removeClass('disabled');
            $step.removeClass('disabled');
            $sidebarItem.removeClass('disabled');
            self.enableBtn($nextBtn);
        } else {
            $stepAltSubStep.not('.active').addClass('disabled');
            $step.not('.active').addClass('disabled');
            $sidebarItem.not('.active').addClass('disabled');
            self.disableBtn($nextBtn);
        }
    };

    this.requiredChange = function () {
        $(self.modal + " [required]").each(function () {
            $(this).on('change keyup load focus', function () {
                self.validateRequired($(this));
            })
        });
    };

    this.selectChange = function () {
        $(self.modal + ' select').selectpicker();
        $(self.modal + " select").each(function () {
            $(this).on('changed.bs.select', function (event, newIndex) {
                let result = '';
                $(this).find('option').each(function (index) {
                    $(this).removeAttr('selected');
                    $(self.modal + ' .option-value-' + $(this).val()).addClass('hidden');
                    if (index === newIndex) {
                        result = $(this).attr('selected', 'selected').val();
                    }
                });
                if (result) {
                    $(self.modal + ' .option-value-' + result).removeClass('hidden');
                    $(self.modal + ' .option-value-' + result + ' [required]').each(function () {
                        self.validateRequired($(this));
                    });
                }

                // update hidden attributes on all following dependant elements. Add anything here you need to update.
                $(self.modal + " input[type='checkbox'].switch").trigger('switchChange.bootstrapSwitch');
            });
        });
    };

    this.switchChange = function () {
        $(self.modal + " input[type='checkbox'].switch").bootstrapSwitch();
        $(self.modal + " input[type='checkbox'].switch").each(function () {
            $(this).on('switchChange.bootstrapSwitch', function (event, state) {
                let on = $(self.modal + " " + $(this).data('on-selector') + '-' + $($(this).data('val-selector')).val());
                let off = $(self.modal + " " + $(this).data('off-selector') + '-' + $($(this).data('val-selector')).val());
                $(self.modal + ' .switchChangeWasHidden').addClass('hidden').removeClass('switchChangeWasHidden');
                if (state) {
                    off.addClass('hidden');
                    on.removeClass('hidden').addClass('switchChangeWasHidden');
                    on.find('[required]').each(function () {
                        self.validateRequired($(this));
                    });
                } else {
                    on.addClass('hidden');
                    off.removeClass('hidden').addClass('switchChangeWasHidden');
                    off.find('[required]').each(function () {
                        self.validateRequired($(this));
                    });
                }
            });
        });
    };

    this.resetToInitialState = function () {
        // drop click event listeners
        $(self.modal + " .wizard-pf-steps-alt-indicator").off('click');
        $(self.modal + " .wizard-pf-step-alt > a").off('click');
        $(self.modal + " select").selectpicker('destroy');
        $(self.modal + " .dropdown-menu li").each(function () {
            $(this).removeClass('selected');
        });

        $(self.modal + " [required]").each(function () {
            $(this).off('change')
        });
        $(self.modal + " form").off("submit");
        $("body").off("click");

        // reset final step
        $(self.modal + " .wizard-pf-process").removeClass("hidden");
        $(self.modal + " .wizard-pf-complete").addClass("hidden");
        // reset loading message
        $(self.modal + " .wizard-pf-contents").addClass("hidden");
        $(self.modal + " .wizard-pf-loading").removeClass("hidden");
        // remove tabs and tab groups
        $(self.modal + " .wizard-pf-steps").addClass("hidden");
        $(self.modal + " .wizard-pf-sidebar").addClass("hidden");
        // reset buttons in final step
        $(self.modal + " .wizard-pf-close").addClass("hidden");
        $(self.modal + " .wizard-pf-cancel").removeClass("hidden");
        $(self.modal + " .wizard-pf-next").removeClass("hidden").find(".wizard-pf-button-text").text("Next");
        // reset input fields
        $(self.modal + " .form-control").val("");
        $(self.modal + " .form-control[value]").val(function () {
            return $(this).attr('value');
        });
    };

    // Cancel/Close button clicked
    this.cancelBtnClick = function () {
        $(self.modal + " .wizard-pf-dismiss").click(function () {
            let idName = $(self.modal).data('idName');
            let data = {};
            data[idName] = $(self.modal).data(idName);
            $.ajax({
                accepts: {
                    mycustomtype: 'application/json'
                },
                url: '/api/' + $(self.modal).data('endpoint') + '/add/abort',
                method: 'post',
                data: data,
                success: function () {
                    $(self.modal).modal('hide');
                    self.resetToInitialState();
                }
            })
        });
    };

    // when the user clicks Next/Back, then the next/previous tab and contents display
    this.wizardPaging = function (direction) {
        // get n.n value of next tab using the index of next tab in tabs array
        let tabIndex = ($.inArray(self.currentTab, self.tabs)) + direction;
        let newTab = self.tabs[tabIndex];
        // add/remove active class from current tab group
        // included math.round to trim off extra .000000000002 that was getting added
        if (0 + newTab !== Math.round(10 * (direction * .1 + self.currentTab)) / 10) {
            // this statement is true when the next tab is in the next tab group
            // if next tab is in next tab group (e.g. next tab data-tab value is
            // not equal to current tab +.1) then apply active class to next
            // tab group and step, and update the value for var currentGroup +/-1
            self.currentGroup = self.currentGroup + direction;
            self.updateTabGroup();
        }
        self.currentTab = newTab;
        // remove active class from active tab in current tab group
        $(self.modal + " .list-group[data-tabgroup='" + self.currentGroup + "'] .list-group-item.active").removeClass("active");
        // apply active class to new current tab and associated contents
        self.updateActiveTab();
    };

    // This code keeps the same contents div active, but switches out what
    // contents display in that div (i.e. replaces process message with
    // success message).
    this.finish = function () {
        self.disableBtn($(self.modal + " .wizard-pf-back")); // if Back remains enabled during this step, then the Close button needs to be removed when the user clicks Back
        self.disableBtn($(self.modal + " .wizard-pf-next"));
        // disable progress link navigation
        $(self.modal + " .wizard-pf-step").addClass('disabled');
        $(self.modal + " .wizard-pf-step-alt").addClass('disabled');
        $(self.modal + " .wizard-pf-step-alt .wizard-pf-step-alt-substep").addClass('disabled');
        $(self.modal + " .wizard-pf-sidebar .list-group-item").addClass('disabled');

        let idName = $(self.modal).data('idName');
        let endpoint = $(self.modal).data('endpoint');

        const id = $(self.modal).data(idName);
        const formData = [
            ...$(`${self.modal} form`).serializeArray(),
            {name: idName, value: id}
        ]

        const data = formData.reduce((acc, curr) => {
            // split empty values
            if (!curr.value) {
                return acc;
            }

            // hacky way to have config in a separate object
            if (curr.name.startsWith('config.')) {
               const name = curr.name.split('.')[1];
               if (!acc['config']) {
                   acc['config'] = {};
               }
               acc['config'][name] = curr.value;
               return acc;
            }

            acc[curr.name] = curr.value;
            return acc;
        }, {});

        $.ajax({
            url: '/api/' + endpoint,
            method: 'post',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function (data) {
                $(self.modal + " .wizard-pf-dismiss").unbind('click').click(function () {
                    $(self.modal).modal('hide');
                    loadList($(self.modal).data('containerEndpoint'), $(self.modal).data('containerSelector'), true);
                    if (data.hasOwnProperty('notification')) {
                        $(data.notification).prependTo($('#notificationContainer'));
                    }
                });
            },
            complete: function () {
                $(self.modal + " .wizard-pf-cancel").addClass("hidden");
                $(self.modal + " .wizard-pf-next").addClass("hidden");
                $(self.modal + " .wizard-pf-close").removeClass("hidden");
                $(self.modal + " .wizard-pf-process").addClass("hidden");
                $(self.modal + " .wizard-pf-complete").removeClass("hidden");
            }
        });
    };
};

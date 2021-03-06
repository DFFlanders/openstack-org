<schedule-grid-events>
    <div class="row" if={ events.length > 0 }>
        <div class="col-md-12">
            <schedule-event each={ events } show_date={ show_date }></schedule-event>
        </div>
    </div>
    <div class="row" if={ events.length === 0 }>
        <div class="col-md-12">
            <p>* There are not events that match your search criteria. </p>
        </div>
    </div>
    <script>

        this.summit                   = opts.summit;
        this.events                   = [];
        this.schedule_filters         = opts.schedule_filters;
        this.search_url               = opts.search_url;
        this.schedule_api             = opts.schedule_api;
        this.base_url                 = opts.base_url;
        this.default_event_color      = opts.default_event_color;
        this.current_filter           = null;
        this.show_date                = false;
        var self                      = this;

        this.on('mount', function(){

        });

        this.schedule_api.on('beforeEventsRetrieved', function(){
            $('#events-container').ajax_loader();
        });

        this.schedule_api.on('eventsRetrieved',function(data) {
            self.show_date    = data.show_date;
            self.events       = data.events;
            self.update();
            console.log(self.events.length +' events retrieved ...');
            self.applyFilters();
            window.setTimeout(function(){$('#events-container').ajax_loader('stop');}, 1000);
        });

        this.schedule_filters.on('scheduleFiltersChanged', function(filters){
            self.current_filter = filters;
            self.applyFilters();
        });

        isFilterEmpty() {
            return self.isSummitTypesFilterEmpty() && self.isEventTypesFilterEmpty() && self.isTracksFilterEmpty() && self.isLevelsFilterEmpty() && self.isTagsFilterEmpty() && self.isMyScheduleFilterEmpty();
        }

        isEventTypesFilterEmpty() {
            return (self.current_filter.event_types === null || self.current_filter.event_types.length === 0);
        }

        isSummitTypesFilterEmpty() {
            return (self.current_filter.summit_types === null || self.current_filter.summit_types.length === 0);
        }

        isTracksFilterEmpty() {
            return (self.current_filter.tracks === null || self.current_filter.tracks.length === 0);
        }

        isLevelsFilterEmpty() {
            return (self.current_filter.levels === null || self.current_filter.levels.length === 0);
        }

        isTagsFilterEmpty() {
            return (self.current_filter.tags === null || self.current_filter.tags.length === 0);
        }

        isMyScheduleFilterEmpty() {
            return (!self.current_filter.own);
        }

        applyFilters(){
            $('.event-row').show();
            if(!self.isFilterEmpty()){
                    console.log('doing filtering ...');
                    for(var e of self.events){
                        e.show = true;
                        //summit types
                        if(!self.isSummitTypesFilterEmpty())
                            e.show &= e.summit_types_id.some(function(v) { return self.current_filter.summit_types.indexOf(v.toString()) != -1; });
                        if(!e.show){ $('#event_'+e.id).hide(); continue;}
                        //eventypes
                        if(!self.isEventTypesFilterEmpty())
                            e.show &= self.current_filter.event_types.indexOf(e.type_id.toString()) > -1;
                        if(!e.show){ $('#event_'+e.id).hide(); continue;}
                        //tracks
                        if(!self.isTracksFilterEmpty() && e.hasOwnProperty('track_id'))
                            e.show &= self.current_filter.tracks.indexOf(e.track_id.toString()) > -1;
                        if(!e.show){ $('#event_'+e.id).hide(); continue;}
                        //level
                        if(!self.isLevelsFilterEmpty() && e.hasOwnProperty('level'))
                            e.show &= self.current_filter.levels.indexOf(e.level.toString()) > -1;
                        if(!e.show){ $('#event_'+e.id).hide(); continue;}
                        //tags
                        if(!self.isTagsFilterEmpty())
                            e.show &= e.tags_id.some(function(v) { return self.current_filter.tags.indexOf(v.toString()) != -1; });
                        if(!e.show){ $('#event_'+e.id).hide(); continue;}
                        //my schedule
                        if(self.current_filter.own)
                            e.show &= e.own;
                        if(!e.show){ $('#event_'+e.id).hide(); continue;}
                        $('#event_'+e.id).show();
                    }
                    console.log('filtering finished ...');
            }
        }
    </script>
</schedule-grid-events>
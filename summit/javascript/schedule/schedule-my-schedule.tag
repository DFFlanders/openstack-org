<schedule-my-schedule>

    <div class="panel panel-default" each="{ key, day in events }">
        <div class="panel-heading">{ key }</div>

        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Room</th>
                    <th>RSVP</th>
                </tr>
            </thead>
            <tbody>
                <tr each={ event in day } data-id="{ event.id }">
                    <td>{ event.start_time } - { event.end_time }</td>
                    <td><a href="{ base_url+'events/'+ event.id }" target="_blank">{ event.title }</a></td>
                    <td if={ should_show_venues == 1 }>{ event.room }</td>
                    <td if={ should_show_venues == 0 }>TBD</td>
                    <td>
                        <a href="{ event.rsvp }" if={ event.rsvp != ''}>RSVP</a>
                        <span if={ event.rsvp == '' }> - </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
        this.events             = opts.events;
        this.should_show_venues = opts.should_show_venues
        this.base_url           = opts.base_url;
        var self                = this;


        this.on('mount', function() {
        });


    </script>

</schedule-my-schedule>
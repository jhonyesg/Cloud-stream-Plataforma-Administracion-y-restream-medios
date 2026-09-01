@once
<script>
window.restreamUrls = {
    client: {
        index:   @json(route('client.restream.channels.index', ['channel' => 'CID'])),
        store:   @json(route('client.restream.channels.store', ['channel' => 'CID'])),
        show:    @json(route('client.restream.channels.show', ['channel' => 'CID', 'target' => 'TID'])),
        update:  @json(route('client.restream.channels.update', ['channel' => 'CID', 'target' => 'TID'])),
        destroy: @json(route('client.restream.channels.destroy', ['channel' => 'CID', 'target' => 'TID'])),
        start:   @json(route('client.restream.channels.start', ['channel' => 'CID', 'target' => 'TID'])),
        stop:    @json(route('client.restream.channels.stop', ['channel' => 'CID', 'target' => 'TID'])),
        log:     @json(route('client.restream.channels.log', ['channel' => 'CID', 'target' => 'TID'])),
        accountsIndex:   @json(route('client.restream.accounts.index')),
        accountConnect:  @json(route('client.restream.accounts.connect', ['platform' => 'PLATFORM'])),
        accountDisconnect: @json(route('client.restream.accounts.destroy', ['platform' => 'PLATFORM'])),
    },
    admin: {
        store:   @json(route('admin.restream.store', ['channel' => 'CID'])),
        show:    @json(route('admin.restream.show', ['channel' => 'CID', 'target' => 'TID'])),
        update:  @json(route('admin.restream.update', ['channel' => 'CID', 'target' => 'TID'])),
        destroy: @json(route('admin.restream.destroy', ['channel' => 'CID', 'target' => 'TID'])),
        start:   @json(route('admin.restream.start', ['channel' => 'CID', 'target' => 'TID'])),
        stop:    @json(route('admin.restream.stop', ['channel' => 'CID', 'target' => 'TID'])),
        log:     @json(route('admin.restream.log', ['channel' => 'CID', 'target' => 'TID'])),
    },
};
</script>
@endonce
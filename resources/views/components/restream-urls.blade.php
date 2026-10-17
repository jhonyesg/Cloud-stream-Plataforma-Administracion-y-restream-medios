@once
<script>
window.restreamUrls = {
    client: {
        index:   @json(route('client.restream.channels.index', ['channel' => 'CID'])),
        store:   @json(route('client.restream.channels.store', ['channel' => 'CID'])),
        show:    @json(route('client.restream.channels.show', ['channel' => 'CID', 'target' => 'TID'])),
        update:  @json(route('client.restream.channels.update', ['channel' => 'CID', 'target' => 'TID'])),
        destroy: @json(route('client.restream.channels.destroy', ['channel' => 'CID', 'target' => 'TID'])),
        forceDestroy: @json(route('client.restream.channels.force-destroy', ['channel' => 'CID', 'target' => 'TID'])),
        start:   @json(route('client.restream.channels.start', ['channel' => 'CID', 'target' => 'TID'])),
        stop:    @json(route('client.restream.channels.stop', ['channel' => 'CID', 'target' => 'TID'])),
        deactivate: @json(route('client.restream.channels.deactivate', ['channel' => 'CID', 'target' => 'TID'])),
        restore: @json(route('client.restream.channels.restore', ['channel' => 'CID', 'target' => 'TID'])),
        clean:   @json(route('client.restream.channels.clean-broadcast', ['channel' => 'CID', 'target' => 'TID'])),
        forceCloseYoutube: @json(route('client.restream.channels.force-close-youtube', ['channel' => 'CID', 'target' => 'TID'])),
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
        forceDestroy: @json(route('admin.restream.force-destroy', ['channel' => 'CID', 'target' => 'TID'])),
        start:   @json(route('admin.restream.start', ['channel' => 'CID', 'target' => 'TID'])),
        stop:    @json(route('admin.restream.stop', ['channel' => 'CID', 'target' => 'TID'])),
        deactivate: @json(route('admin.restream.deactivate', ['channel' => 'CID', 'target' => 'TID'])),
        restore: @json(route('admin.restream.restore', ['channel' => 'CID', 'target' => 'TID'])),
        forceCloseYoutube: @json(route('admin.restream.force-close-youtube', ['channel' => 'CID', 'target' => 'TID'])),
        log:     @json(route('admin.restream.log', ['channel' => 'CID', 'target' => 'TID'])),
        accountsIndex: @json(route('admin.restream.accounts.index')),
        accountConnect: @json(route('admin.restream.accounts.connect', ['user' => 'USERID', 'platform' => 'PLATFORM'])),
        accountDisconnect: @json(route('admin.restream.accounts.destroy', ['user' => 'USERID', 'platform' => 'PLATFORM'])),
    },
};
</script>
@endonce
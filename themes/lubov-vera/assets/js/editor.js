(function () {
    var blocksToRemove = ['core/quote'];

    function removeQuoteBlocks() {
        if (wp && wp.blocks && wp.blocks.unregisterBlockType) {
            blocksToRemove.forEach(function (name) {
                try {
                    wp.blocks.unregisterBlockType(name);
                } catch (e) {}
            });
        }
    }

    if (wp && wp.domReady) {
        wp.domReady(removeQuoteBlocks);
    }
})();

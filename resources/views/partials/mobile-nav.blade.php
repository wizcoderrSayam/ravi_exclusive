<div class="offcanvas offcanvas-start mnav" tabindex="-1" id="mnav" aria-labelledby="mnavTitle">
  <h3 id="mnavTitle">Shop <button type="button" class="close-x" data-bs-dismiss="offcanvas" aria-label="Close">×</button></h3>
  <div id="mnavList">
    @foreach ($menuCategories as $menuCategory)
      <details>
        <summary>{{ $menuCategory['name'] }}</summary>
        <a href="{{ $menuCategory['url'] }}">All {{ $menuCategory['name'] }}</a>
        @foreach ($menuCategory['links'] as $link)
          <a href="{{ $link['url'] }}">{{ $link['name'] }}</a>
        @endforeach
      </details>
    @endforeach
    <details>
      <summary>More</summary>
      <a href="{{ route('shop.new-arrivals') }}">New Arrivals</a>
      <a href="{{ route('shop.wishlist') }}">Wishlist</a>
    </details>
  </div>
  <div class="mnav-help">Need help? <a href="tel:+{{ $storeWhatsApp->digits }}">{{ $storeWhatsApp->display }}</a></div>
</div>

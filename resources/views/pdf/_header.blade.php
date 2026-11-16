<table class="head">
    <tr>
        <td>
            <div class="brand">{{ $company['name'] }}</div>
            @if ($company['tagline'])<div class="muted">{{ $company['tagline'] }}</div>@endif
            @if ($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
            <div class="muted">{{ implode(' · ', array_filter([$company['phone'], $company['email']])) }}</div>
        </td>
        <td class="right">
            <h1>{{ $heading }}</h1>
            <div>{{ $number }}</div>
        </td>
    </tr>
</table>
<div class="rule"></div>

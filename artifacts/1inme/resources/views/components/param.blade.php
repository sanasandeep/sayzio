@props(['name', 'type' => 'string', 'req' => 'false'])
<tr>
    <td class="py-1.5 px-2 font-mono text-violet-300 text-[13px]">
        {{ $name }}
        @if($req === 'true' || $req === true)
            <span class="text-[10px] text-rose-400 font-sans ml-1">required</span>
        @endif
    </td>
    <td class="py-1.5 px-2 text-amber-300 text-[12px] font-mono">{{ $type }}</td>
    <td class="py-1.5 px-2 text-gray-400 text-[13px]">{{ $slot ?? '' }}</td>
</tr>

<br><br>

<table id="tabela-quadro" class="table  text-center table-bordered">
    <thead class="thead-dark">
        <tr>

            <th scope="col">Unidade</th>
            @foreach ($matrix as $key => $valor)
                <th scope="col">{{ ucfirst($key) }}</th>
            @endforeach

        </tr>
    </thead>
    <tbody>

        @foreach ($divisions as $division)
            <tr>

                @if ($division->name != null)
                    <th scope="row"> {{ $division->name }}</th>
                @endif
                @foreach ($matrix as $key => $value)
                    @if ($division->name != null)
                        <td>{{ isset($value[$division->name]) ? $value[$division->name] : '0' }}</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

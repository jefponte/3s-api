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
                <th scope="row"> {{ $division->name }}</th>
                @foreach ($matrix as $key => $value)
                    <td>{{isset($value[$division->name]) ? $value[$division->name] : "0" }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
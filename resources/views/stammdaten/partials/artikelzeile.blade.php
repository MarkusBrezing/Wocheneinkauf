{{--
    Eine Zeile des Artikelkatalogs.

    Ausgelagert, seit die Tabelle nach Warengruppen gegliedert ist: Die Zeile
    steht sonst in jedem Abschnitt noch einmal im Quelltext, und die siebte
    Kopie ist die, die man beim Ändern vergisst.

    Erwartet $produkt, $f (die Kennung des zugehörigen Formulars), $gruppen
    und $maerkte.
--}}
<tr>
    <td><input form="{{ $f }}" name="name" type="text" value="{{ $produkt->name }}" required></td>
    <td>
        <select form="{{ $f }}" name="product_group_id">
            <option value="">— ohne —</option>
            @foreach ($gruppen as $eintrag)
                <option value="{{ $eintrag->id }}" @selected($produkt->product_group_id === $eintrag->id)>
                    {{ $eintrag->label }}
                </option>
            @endforeach
        </select>
    </td>
    <td><input form="{{ $f }}" name="default_unit" type="text" value="{{ $produkt->default_unit }}"></td>
    <td style="text-align:center">
        <input form="{{ $f }}" type="checkbox" name="is_pantry_staple" value="1"
               class="vorrat-an" @checked($produkt->is_pantry_staple)
               aria-label="{{ $produkt->name }} ist Grundvorrat">
    </td>
    <td>
        {{-- Wie viel davon im Haus sein soll. Der Assistent „Neue Woche" und
             die Bestandsaufnahme rechnen damit. Angezeigt ohne
             Nachkommanullen -- „2.000" ist in hundert Zeilen nur Rauschen. --}}
        <input form="{{ $f }}" name="pantry_target_amount" type="number"
               step="0.5" min="0" value="{{ $produkt->sollmengeFeldwert() }}"
               aria-label="Sollmenge {{ $produkt->name }}">
    </td>
    <td>
        <details class="verfuegbarkeit">
            <summary>{{ $produkt->verfuegbarkeitshinweis() ?? 'überall' }}</summary>
            <div class="auswahl">
                <label>
                    <input form="{{ $f }}" type="radio" name="verfuegbarkeit" value="ueberall"
                           @checked($produkt->istUeberallErhaeltlich())> überall
                </label>
                <label>
                    <input form="{{ $f }}" type="radio" name="verfuegbarkeit" value="auswahl"
                           @checked(! $produkt->istUeberallErhaeltlich())> nur in diesen Märkten
                </label>
                @foreach ($maerkte as $markt)
                    <label style="margin-left:1.2rem">
                        <input form="{{ $f }}" type="checkbox" name="maerkte[]" value="{{ $markt->id }}"
                               @checked($produkt->markets->contains($markt))> {{ $markt->name }}
                    </label>
                @endforeach
            </div>
        </details>
    </td>
    <td class="knopfspalte">
        <button form="{{ $f }}" type="submit" class="knopf-schlicht">Speichern</button>
        <button form="loeschen{{ $produkt->id }}" type="submit" class="knopf-schlicht">Löschen</button>
    </td>
</tr>

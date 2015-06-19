
$(document).ready(function(){

    $('#contratos').change(function()
    {
        $("#cosa option").remove();
        valor = $(this).val();

        console.log(valor);
        $.ajax({
            type: "GET",
            url: "rellenarContrato/"+valor,

            success : function(response)
            {
                $("#cosa").append(response);
            }
        });
        //}
    });

    // inputs segun cisternas
    $('#cisternas').change(function()
    {
        $("#inputs input").remove();
        $("#inputs").show();
        valor = $(this).val();

        for(i = 1;i <= valor;i++){
            $("#inputs").append("<input type='text' name='matricula_"+i+"'>");
        }

    });
});
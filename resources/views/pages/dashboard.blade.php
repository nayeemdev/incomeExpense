@extends('layouts.master')

@push('css')
<style type="text/css">
.calculator{
    display: flex;
    justify-content: center;
    align-items: center;
}

.calculator__main{
    box-shadow: 5px 5px 15px 0px rgba(0,0,0,0.75);
    padding: 20px;
    background-color: #252525ad;
    border-radius: 20px;
}

.calculator__stack{
    font-size: 16px;
    background-color: #ffffff;
    text-align: right;
}

.calculator__summery{
    font-size: 30px;
    background-color: #ffffff;
    text-align: right;
}

.calculator__row .calculator__btn{
    display: inline-block;
    width: 70px;
    height: 60px;
}

.calculator__row div:hover{
    cursor: pointer;
    color: #616161;
    transition-duration: 0.4s;
}

.calculator__row:not(:last-child){
    border-bottom: solid 1px #ddd;
}

.calculator__btn:not(:last-child){
    border-right: solid 1px #ddd;
}

.calculator__btn{
    height: 60px;
    line-height: 60px;
    text-align: center;
}

.calculator__btn--dark{
    color: #ffffff;
    background-color: #252525;
}

.calculator__btn--violet{
    color: #ffffff;
    background-color: #7f00b1;
}

.calculator__btn--yellow{
    background-color: #fbff13;
}

.calculator__btn--red{
    color: #ffffff;
    background-color: #a52828;
}

.modal-backdrop {
   background-color: #aaa;
}

.modal-content {
    background-color: initial;
    border: none;
}


button.close {
        background: blue;
        border: 0 none !important;
        color: #fff;
        font-size: 34px;
        height: 50px;
        line-height: 1;
        margin: 0 auto;
        opacity: 1;
        text-align: center;
        text-shadow: none;
        -webkit-transition: background 0.2s ease-in-out;
        transition: background 0.2s ease-in-out;
        vertical-align: top;
        width: 50px;
        border-radius: 50%;
        padding-bottom : 5px;
        margin-bottom : 5px;
    }
    </style>
@endpush

@section('content')

@if (session('status'))
    <div class="alert alert-success" role="alert">
        {{ session('status') }}
    </div>
@endif

<div class="container-fluid">
    <!-- Breadcrumbs-->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="#">Dashboard</a>
        </li>
        <li class="breadcrumb-item active">Overview</li>
    </ol>
    <!-- Icon Cards-->
    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-primary o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-comments"></i>
                    </div>
                    <div class="mr-5">26 New Messages!</div>
                </div>
                <a class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-warning o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-list"></i>
                    </div>
                    <div class="mr-5">11 New Tasks!</div>
                </div>
                <a class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-success o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-shopping-cart"></i>
                    </div>
                    <div class="mr-5">123 New Orders!</div>
                </div>
                <a class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-danger o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-life-ring"></i>
                    </div>
                    <div class="mr-5">13 New Tickets!</div>
                </div>
                <a class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>
    </div>
</div>
<div class="calculatorBtnDiv">
  <button data-toggle="modal" data-target="#calModal"  data-backdrop="static" data-keyboard="false" class="btn btn-primary calButton"><i class="fa fa-calculator"></i></button>
</div>

<!-- Modal -->
<div class="modal fade" id="calModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <div class="calculator">
        <div class="calculator__main">

            <div class="calculator__stack">
                12,546+4,863*6/1,000
            </div>
            
            <div class="calculator__summery">
                9,000,000,000.5689
            </div>
    
            <!--Comment between div : remove space each element-->
            <div class="calculator__row">
                <div class="calculator__btn calculator__btn--red" value="clear">C</div><!--     
                --><div class="calculator__btn calculator__btn--red" value="clear-entry">CE</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="del">del</div><!--
                --><div class="calculator__btn calculator__btn--violet" value="/">÷</div>
            </div>
    
            <div class="calculator__row">
                <div class="calculator__btn calculator__btn--dark" value="7">7</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="8">8</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="9">9</div><!--
                --><div class="calculator__btn calculator__btn--violet" value="*">x</div>
            </div>
    
            <div class="calculator__row">
                <div class="calculator__btn calculator__btn--dark" value="4">4</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="5">5</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="6">6</div><!--
                --><div class="calculator__btn calculator__btn--violet" value="-">-</div>
            </div>
    
            <div class="calculator__row">
                <div class="calculator__btn calculator__btn--dark" value="1">1</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="2">2</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="3">3</div><!--
                --><div class="calculator__btn calculator__btn--violet" value="+">+</div>
            </div>
    
            <div class="calculator__row">
                <div class="calculator__btn calculator__btn--dark" value=".">.</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="0">0</div><!--
                --><div class="calculator__btn calculator__btn--dark" value="00">00</div><!--
                --><div class="calculator__btn calculator__btn--yellow" value="sum">=</div>
            </div>

        </div>

      </div>
    </div>
  </div>
</div>

@endsection

@push('js')
    <script type="text/javascript">
       var Controller = (function(){
    //Global Variable
    var val, stackArr, total;

    val = '';
    stackArr = [];
    total = 0;

    //------------------------------------------------------------------
    //DOM Element Ref.
    var DOM = {
        summery: document.querySelector('.calculator__summery'),
        stack: document.querySelector('.calculator__stack')
    }
    //------------------------------------------------------------------
    //function : Clear UI 
    var clearUI = function(){
        total = 0;
        stackArr = [];
        DOM.summery.textContent = "0";
        DOM.stack.textContent = "0";
    }
    //------------------------------------------------------------------
    //function : format Number (turn 5000 --> 5,000.00) 
    var formatNum = function(num){

        num = Math.abs(num);
        num = num.toFixed(2);
        var numSplit = num.split(".");
        ;
        var int = parseInt(numSplit[0]);
        var dec = numSplit[1];

        return int.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,') + '.' + dec;
    }

    //------------------------------------------------------------------
    //Main Function : check operator on click 
    var checkOps = function(){
        if(val){

            // btn -> " = " btn
            if(val === "sum"){
                var merge = document.querySelector('.calculator__stack').innerHTML;
                total = eval(merge);
                DOM.stack.textContent = "0";
                DOM.summery.textContent = formatNum(total);
                stackArr = [];
                stackArr.push(total);

            // btn -> " del "
            }else if(val === "del"){
                stackArr.pop();
                stackArr.length === 0 ?  DOM.stack.textContent = "0" : DOM.stack.textContent = stackArr.join('');

            // btn -> " C "
            }else if(val === "clear"){
                clearUI();

            // btn -> " CE " 
            }else if(val === "clear-entry"){
                DOM.summery.textContent = formatNum(total);
                DOM.stack.textContent = "0";
                stackArr = [];
                stackArr.push(total);

            // btn -> (Number , ". [dot]")
            }else{
                stackArr.push(val);
                DOM.summery.textContent = "0";
                DOM.stack.textContent = stackArr.join('');
            }   
        }
    }

    //------------------------------------------------------------------
    // EventLister : Setup
    var getEvent = function(){
        document.addEventListener('click', function(e){
            // 1. get Value form html
            val = e.target.getAttribute('value');
            // 2. Call checkOps method
            checkOps();
        });
    }

    //------------------------------------------------------------------

    return{
        init: function(){
            //1. Cennect with Script.js
            console.log("Script.js : connecting... ");
            //2. Clear UI prepare for process
            clearUI();
            //3. Call method : Main System
            getEvent();
        }
    }
})();

//Start Program
Controller.init();
    </script>
@endpush

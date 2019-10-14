
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

import React from "react";
import { Modal } from "react-bootstrap";
import _ from "lodash";
import { Button } from "components";
import { useDispatch } from "react-redux";
import { set_temp_data } from "modules/Home/home.slice";
import SecureLS from "secure-ls";
import ThemeObj from "modules/theme-config/theme-config";
import { useMediaPredicate } from "react-media-hook";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const RenewalClaim = (props) => {
  const lessthan768 = useMediaPredicate("(max-width: 768px)");
  const dispatch = useDispatch();
  return (
    <Modal
      {...props}
      size="md"
      aria-labelledby="contained-modal-title-vcenter"
      centered
    >
      <Modal.Header closeButton>
        <Modal.Title id="contained-modal-title-vcenter">
          Confirmation Required
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        <p>Any claims made in your existing policy?</p>
      </Modal.Body>
      <Modal.Footer>
        <Button
          type="button"
          buttonStyle="outline-solid"
          onClick={() => {
            return [
              dispatch(props?.CancelAll(true)),
              dispatch(
                set_temp_data({
                  isClaim: "Y",
                  isClaimVerified: "Y",
                  // ncb: "0%",
                  newNcb: "0%",
                  isNcbVerified: "Y",
                })
              ),
              dispatch(props?.CancelAll(false)),
              props?.onHide(),
            ];
          }}
          hex1={
            Theme?.paymentConfirmation?.Button?.hex1
              ? Theme?.paymentConfirmation?.Button?.hex1
              : "#4ca729"
          }
          hex2={
            Theme?.paymentConfirmation?.Button?.hex2
              ? Theme?.paymentConfirmation?.Button?.hex2
              : "#4ca729"
          }
          borderRadius="5px"
          color={
            Theme?.PaymentConfirmation?.buttonTextColor
              ? Theme?.PaymentConfirmation?.buttonTextColor
              : "white"
          }
          style={{ ...(lessthan768 && { width: "100%" }) }}
        >
          <text
            style={{
              fontSize: "15px",
              padding: "-20px",
              margin: "-20px -5px -20px -5px",
              fontWeight: "400",
            }}
          >
            Yes
          </text>
        </Button>
        <Button
          type="submit"
          buttonStyle="outline-solid"
          onClick={() => {
            return [
              dispatch(props?.CancelAll(true)),
              dispatch(set_temp_data({ isClaim: "N", isClaimVerified: "Y" })),
              dispatch(props?.CancelAll(false)),
              props?.onHide(),
            ];
          }}
          hex1={
            Theme?.paymentConfirmation?.Button?.hex1
              ? Theme?.paymentConfirmation?.Button?.hex1
              : "#4ca729"
          }
          hex2={
            Theme?.paymentConfirmation?.Button?.hex2
              ? Theme?.paymentConfirmation?.Button?.hex2
              : "#4ca729"
          }
          borderRadius="5px"
          color={
            Theme?.PaymentConfirmation?.buttonTextColor
              ? Theme?.PaymentConfirmation?.buttonTextColor
              : "white"
          }
          style={{ ...(lessthan768 && { width: "100%" }) }}
        >
          <text
            style={{
              fontSize: "15px",
              padding: "-20px",
              margin: "-20px -5px -20px -5px",
              fontWeight: "400",
            }}
          >
            {"No"}
          </text>
        </Button>
      </Modal.Footer>
    </Modal>
  );
};

export default RenewalClaim;

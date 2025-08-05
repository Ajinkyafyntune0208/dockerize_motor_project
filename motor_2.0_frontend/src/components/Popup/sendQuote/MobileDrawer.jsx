import { Backdrop, Drawer } from "@mui/material";
import React from "react";
import { CloseButton, MobileDrawerBody } from "./style";

export const MobileDrawer = ({
  drawer,
  setDrawer,
  onClose,
  setSendPdf = () => {},
  msg,
  content,
  content2,
  openGarageModal,
  cashlessContent,
  setShareProposalPayment= () => {}
}) => {
  const handleBackdropClick = (event) => {
    event.preventDefault();
  };

  const closingProcedure = () => {
    setDrawer(false);
    onClose(false);
    setSendPdf(false);
    setShareProposalPayment(false);
  };

  return (
    <div style={{ borderRadius: "5% 5% 0% 0%" }}>
      <Drawer
        anchor={"bottom"}
        open={drawer}
        style={setSendPdf ? { zIndex: "100000" } : {}}
        onClose={() => {
          closingProcedure();
        }}
        onOpen={() => {
          setDrawer(true);
        }}
        ModalProps={{
          keepMounted: true,
          disableEscapeKeyDown: true,
          BackdropComponent: Backdrop,
          BackdropProps: { onClick: handleBackdropClick },
        }}
      >
        <MobileDrawerBody>
          <CloseButton
            onClick={() => {
              closingProcedure()
            }}
          >
            <svg
              version="1.1"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
              style={{ height: "25px" }}
            >
              <path
                fill={"#000"}
                d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
              ></path>
              <path fill="none" d="M0,0h24v24h-24Z"></path>
            </svg>
          </CloseButton>

          {openGarageModal ? cashlessContent : msg ? content2 : content}
        </MobileDrawerBody>
      </Drawer>
    </div>
  );
};

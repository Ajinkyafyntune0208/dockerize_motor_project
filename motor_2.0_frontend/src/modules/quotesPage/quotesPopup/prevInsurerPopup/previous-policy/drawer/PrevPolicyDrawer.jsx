import React from "react";
import Drawer from "@mui/material/Drawer";
import { CloseButton, MobileDrawerBody } from "../style/style";

const PreviousPolicyDrawer = ({
  drawer,
  setDrawer,
  onClose,
  temp_data,
  content,
}) => {
  return (
    <React.Fragment key={"bottom"} style={{ borderRadius: "5% 5% 0% 0%" }}>
      <Drawer
        anchor={"bottom"}
        open={drawer}
        onClose={() => {
          setDrawer(false);
          onClose(false);
        }}
        onOpen={() => setDrawer(true)}
        ModalProps={{
          keepMounted: true,
          disableEscapeKeyDown: true,
        }}
        onBackdropClick={false}
      >
        <MobileDrawerBody>
          {temp_data?.expiry && (
            <CloseButton
              onClick={() => {
                setDrawer(false);
                onClose(false);
              }}
            >
              <svg
                version="1.1"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                style={{ height: " 25px" }}
              >
                <path
                  fill={"#000"}
                  d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                ></path>
                <path fill="none" d="M0,0h24v24h-24Z"></path>
              </svg>
            </CloseButton>
          )}
          {content}
        </MobileDrawerBody>
      </Drawer>
    </React.Fragment>
  );
};

export default PreviousPolicyDrawer;
